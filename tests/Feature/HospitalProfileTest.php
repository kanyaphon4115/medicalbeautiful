<?php

namespace Tests\Feature;

use App\Models\HospitalProfile;
use App\Models\HospitalVerificationChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HospitalProfileTest extends TestCase
{
    use RefreshDatabase;

    private function hospital()
    {
        return User::factory()->create(['role' => User::ROLE_HOSPITAL]);
    }

    private function fields()
    {
        return ['name' => 'Real Hospital', 'address' => 'Actual address', 'country' => 'Thailand', 'phone' => '021234567', 'email' => 'contact@example.test'];
    }

    public function test_profile_loads_without_creating_or_replacing_account_data()
    {
        $hospital = $this->hospital();
        $this->actingAs($hospital)->get(route('hospital.profile'))->assertOk()->assertSee($hospital->name)->assertSeeInOrder(['Notifications', 'Hospital Profile', 'Settings']);
        $this->assertDatabaseCount('hospital_profiles', 0);
        $this->post(route('hospital.profile.update'), $this->fields())->assertRedirect(route('hospital.profile'));
        $this->assertDatabaseHas('hospital_profiles', ['hospital_id' => $hospital->id, 'name' => 'Real Hospital']);
        $this->assertSame($hospital->email, $hospital->fresh()->email);
        $this->get(route('hospital.profile'))->assertOk()->assertSee('Real Hospital')->assertSee('Actual address');
    }

    public function test_sensitive_changes_are_encrypted_and_require_support_approval()
    {
        $hospital = $this->hospital();
        $profile = HospitalProfile::create(['hospital_id' => $hospital->id, 'registered_name' => 'Approved name', 'license_number' => 'OLD']);
        $this->actingAs($hospital)->post(route('hospital.profile.update'), $this->fields() + ['registered_name' => 'New registered name', 'license_number' => 'NEW'])->assertSessionHasNoErrors();
        $this->assertSame('OLD', $profile->fresh()->license_number);
        $change = HospitalVerificationChange::firstOrFail();
        $this->assertStringNotContainsString('NEW', $change->getRawOriginal('payload'));
        $this->post(route('hospital.verifications.decide', $change), ['decision' => 'approved', 'note' => 'Verified'])->assertForbidden();
        $support = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($support)->get(route('hospital.verifications'))->assertOk()->assertSee('New registered name');
        $this->post(route('hospital.verifications.decide', $change), ['decision' => 'approved', 'note' => 'Verified documents'])->assertRedirect();
        $this->assertSame('NEW', $profile->fresh()->license_number);
        $this->post(route('hospital.verifications.decide', $change), ['decision' => 'approved', 'note' => 'Again'])->assertStatus(409);
        $this->actingAs($hospital)->post(route('hospital.profile.update'), $this->fields() + ['license_number' => 'REJECT'])->assertSessionHasNoErrors();
        $next = HospitalVerificationChange::latest('id')->first();
        $this->actingAs($support)->post(route('hospital.verifications.decide', $next), ['decision' => 'rejected', 'note' => 'Incorrect'])->assertRedirect();
        $this->assertSame('NEW', $profile->fresh()->license_number);
    }

    public function test_private_uploads_are_persistent_and_isolated_to_the_owner_and_support()
    {
        Storage::fake('local');
        $hospital = $this->hospital();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=');
        $this->actingAs($hospital)->post(route('hospital.profile.update'), $this->fields() + ['logo' => UploadedFile::fake()->createWithContent('logo.png', $png), 'gallery' => [UploadedFile::fake()->createWithContent('gallery.png', $png)], 'license_documents' => [UploadedFile::fake()->createWithContent('license.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")]])->assertSessionHasNoErrors();
        $profile = HospitalProfile::firstOrFail();
        Storage::disk('local')->assertExists($profile->logo_path);
        $url = $profile->mediaUrl('logo');
        $this->get($url)->assertOk();
        $doc = HospitalVerificationChange::first()->payload['documents'][0];
        $docUrl = $profile->mediaUrl('verification', $doc['id']);
        $this->get($docUrl)->assertOk();
        $this->actingAs($this->hospital())->get($url)->assertForbidden();
        $this->get($docUrl)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get($docUrl)->assertOk();
        $this->actingAs($hospital)->post(route('hospital.profile.update'), $this->fields() + ['remove_gallery' => [$profile->gallery[0]['id']]])->assertSessionHasNoErrors();
        $this->assertSame([], $profile->fresh()->gallery);
    }

    public function test_invalid_updates_and_non_hospital_accounts_are_blocked()
    {
        $hospital = $this->hospital();
        $this->actingAs($hospital)->post(route('hospital.profile.update'), ['name' => ''])->assertSessionHasErrors(['name', 'address', 'country', 'phone', 'email']);
        $this->assertDatabaseCount('hospital_profiles', 0);
        $this->actingAs(User::factory()->create())->get(route('hospital.profile'))->assertForbidden();
        $this->post(route('hospital.profile.update'), $this->fields())->assertForbidden();
        $this->get(route('hospital.verifications'))->assertForbidden();
    }
}
