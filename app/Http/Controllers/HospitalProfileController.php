<?php

namespace App\Http\Controllers;

use App\Models\HospitalProfile;
use App\Models\HospitalVerificationChange;
use App\Services\ReviewPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HospitalProfileController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isHospital(), 403);
        $profile = HospitalProfile::where('hospital_id', $request->user()->id)->first();
        $pending = $profile?->changes()->where('status', 'pending')->latest('id')->first();
        $latest = $profile?->changes()->latest('id')->first();

        return response()->view('hospital.profile', compact('profile', 'pending', 'latest') + ['categories' => TreatmentRequestController::PROCEDURES])->header('Cache-Control', 'private, no-store');
    }

    private function storeImage($file, array &$written): string
    {
        $clean = app(ReviewPhoto::class)->clean($file);
        $path = 'hospital-profiles/'.Str::uuid().'.'.$clean['extension'];
        Storage::disk('local')->put($path, $clean['bytes']);
        $written[] = $path;

        return $path;
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->isHospital(), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'short_description' => 'nullable|string|max:500', 'description' => 'nullable|string|max:10000', 'address' => 'required|string|max:2000',
            'city' => 'nullable|string|max:255', 'country' => 'required|string|max:100', 'contact_person' => 'nullable|string|max:255', 'phone' => 'required|string|max:40',
            'email' => 'required|email|max:255', 'website' => 'nullable|url:http,https|max:255', 'maps_url' => 'nullable|url:http,https|max:255', 'operating_hours' => 'nullable|string|max:255',
            'treatments' => 'nullable|array', 'treatments.*' => ['string', Rule::in(TreatmentRequestController::PROCEDURES)], 'specialties' => 'nullable|string|max:3000', 'treatment_description' => 'nullable|string|max:10000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120|dimensions:max_width=8192,max_height=8192', 'main' => 'nullable|image|mimes:jpg,jpeg,png|max:5120|dimensions:max_width=8192,max_height=8192',
            'gallery' => 'nullable|array|max:12', 'gallery.*' => 'image|mimes:jpg,jpeg,png|max:5120|dimensions:max_width=8192,max_height=8192',
            'remove_logo' => 'sometimes|boolean', 'remove_main' => 'sometimes|boolean', 'remove_gallery' => 'nullable|array', 'remove_gallery.*' => 'uuid',
            'registered_name' => 'nullable|string|max:255', 'license_number' => 'nullable|string|max:150', 'license_documents' => 'nullable|array|max:5',
            'license_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240', 'remove_documents' => 'nullable|array', 'remove_documents.*' => 'uuid',
        ], [], array_map(fn ($label) => __($label), [
            'name'=>'Hospital Name', 'short_description'=>'Short Description', 'description'=>'Full Description',
            'address'=>'Hospital Address', 'city'=>'City / Province', 'country'=>'Country', 'contact_person'=>'Contact Person',
            'phone'=>'Phone Number', 'email'=>'Email Address', 'website'=>'Website', 'maps_url'=>'Google Maps Location',
            'operating_hours'=>'Operating Hours', 'treatments'=>'Treatments & Specialties', 'specialties'=>'Hospital Specialties',
            'treatment_description'=>'Treatment Descriptions', 'logo'=>'Hospital Logo', 'main'=>'Main Profile Image',
            'gallery'=>'Gallery Photos', 'gallery.*'=>'Gallery Photos', 'registered_name'=>'Registered Hospital Name',
            'license_number'=>'Hospital License Number', 'license_documents'=>'License Documents', 'license_documents.*'=>'License Documents',
        ]));
        $written = [];
        try {
            DB::transaction(function () use ($request, $data, &$written) {
                // Lock the account first to also serialize creation of its first profile.
                $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $profile = HospitalProfile::firstOrNew(['hospital_id' => $request->user()->id]);
                Gate::authorize('update', $profile);
                foreach (['name', 'short_description', 'description', 'address', 'city', 'country', 'contact_person', 'phone', 'email', 'website', 'maps_url', 'operating_hours', 'specialties', 'treatment_description'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $profile->{$field} = $data[$field];
                    }
                }
                if (array_key_exists('treatments', $data)) {
                    $profile->treatments = $data['treatments'];
                }
                foreach (['logo', 'main'] as $kind) {
                    if ($request->boolean('remove_'.$kind)) {
                        $profile->{$kind.'_path'} = null;
                    }if ($request->hasFile($kind)) {
                        $profile->{$kind.'_path'} = $this->storeImage($request->file($kind), $written);
                    }
                }
                $gallery = collect($profile->gallery ?? [])->reject(fn ($p) => in_array($p['id'], $data['remove_gallery'] ?? [], true))->values()->all();
                foreach ($request->file('gallery', []) as $file) {
                    $gallery[] = ['id' => (string) Str::uuid(), 'path' => $this->storeImage($file, $written)];
                }$profile->gallery = $gallery;
                $profile->save();
                $current = $profile->changes()->where('status', 'pending')->latest('id')->first();
                $base = $current?->payload ?? ['registered_name' => $profile->registered_name, 'license_number' => $profile->license_number, 'documents' => $profile->verification_documents ?? []];
                $proposal = ['registered_name' => array_key_exists('registered_name', $data) ? $data['registered_name'] : $base['registered_name'],
                    'license_number' => array_key_exists('license_number', $data) ? $data['license_number'] : $base['license_number'],
                    'documents' => collect($base['documents'])->reject(fn ($d) => in_array($d['id'], $data['remove_documents'] ?? [], true))->values()->all()];
                foreach ($request->file('license_documents', []) as $file) {
                    if ($file->getMimeType() === 'application/pdf') {
                        $path = $file->store('hospital-verification', 'local');
                        $written[] = $path;
                    } else {
                        $path = $this->storeImage($file, $written);
                    }
                    $proposal['documents'][] = ['id' => (string) Str::uuid(), 'path' => $path, 'name' => $file->getClientOriginalName()];
                }
                if ($proposal !== $base) {
                    $profile->changes()->where('status', 'pending')->update(['status' => 'superseded']);
                    $profile->changes()->create(['payload' => $proposal, 'status' => 'pending']);
                }
            });
        } catch (\Throwable $e) {
            foreach ($written as $path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }

        return redirect()->route('hospital.profile')->with('status', 'Hospital profile saved.');
    }

    public function media(HospitalProfile $profile, string $kind, ?string $file = null)
    {
        Gate::authorize('view', $profile);
        $path = null;
        $download = null;
        if (in_array($kind, ['logo', 'main'], true)) {
            $path = $profile->{$kind.'_path'};
        } elseif ($kind === 'gallery') {
            $path = collect($profile->gallery ?? [])->firstWhere('id', $file)['path'] ?? null;
        } elseif ($kind === 'verification') {
            $documents = collect($profile->verification_documents ?? []);
            foreach ($profile->changes as $change) {
                $documents = $documents->concat($change->payload['documents'] ?? []);
            }
            $doc = $documents->firstWhere('id', $file);
            $path = $doc['path'] ?? null;
            $download = $doc['name'] ?? 'license-document';
        }
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $download ? Storage::disk('local')->download($path, $download, $headers) : response()->file(Storage::disk('local')->path($path), $headers);
    }

    public function approvals(Request $request)
    {
        abort_unless($request->user()->isChatSupport(), 403);

        return response()->view('hospital.approvals', ['changes' => HospitalVerificationChange::with('profile.hospital')->latest()->paginate(5)])->header('Cache-Control', 'private, no-store');
    }

    public function decide(Request $request, HospitalVerificationChange $change)
    {
        Gate::authorize('moderate', $change->profile);
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'required|string|max:2000']);
        DB::transaction(function () use ($request, $change, $data) {
            $profile = HospitalProfile::whereKey($change->hospital_profile_id)->lockForUpdate()->firstOrFail();
            $row = HospitalVerificationChange::whereKey($change->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'pending', 409, 'This verification request is no longer pending.');
            if ($data['decision'] === 'approved') {
                $payload = $row->payload;
                $profile->update(['registered_name' => $payload['registered_name'], 'license_number' => $payload['license_number'], 'verification_documents' => $payload['documents'], 'verified_at' => now()]);
            }
            $row->update(['status' => $data['decision'], 'reviewer_id' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['note']]);
        });

        return back()->with('status','Verification request reviewed.');
    }
}
