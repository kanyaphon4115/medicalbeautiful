<?php
namespace App\Http\Controllers;

use App\Models\{TreatmentRequest, Quotation, Booking};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HospitalDashboardController extends Controller
{
    public function index(Request $request) {
        abort_unless($request->user()->isHospital(),403);
        $id = $request->user()->id;
        $requests = TreatmentRequest::availableToHospital($id)->with(['customer','photos','booking','quotations'=>fn($q)=>$q->where('hospital_id',$id)])->latest()->get();
        $quotations = Quotation::where('hospital_id',$id)->with('treatmentRequest.booking')->latest()->get();
        $bookings = Booking::bookingEligible()->where('hospital_id',$id)->with(['treatmentRequest','quotation'])->latest()->get();
        $stats = ['New Requests'=>$requests->filter(fn($r)=>$r->quotations->isEmpty() && !$r->selected_hospital_id)->count(),
            'Quotations Sent'=>$quotations->whereNull('withdrawn_at')->count(), 'Confirmed Bookings'=>$bookings->where('status','Confirmed')->count(),
            'Completed Bookings'=>$bookings->where('status','Completed')->count()];
        return response()->view('hospital.dashboard',compact('requests','quotations','bookings','stats'))->header('Cache-Control','private, no-store');
    }

    public function quote(Request $request, TreatmentRequest $treatmentRequest) {
        abort_unless($request->user()->isHospital(),403);
        $data = $request->validate(['amount'=>['required','numeric','min:0.01','max:9999999999.99'],
            'details'=>['required','string','max:5000'],'valid_until'=>['required','date_format:Y-m-d','after_or_equal:today']]);
        $quotation = DB::transaction(function () use ($request,$treatmentRequest,$data) {
            $item = TreatmentRequest::availableToHospital($request->user()->id)->lockForUpdate()->findOrFail($treatmentRequest->id);
            abort_if($item->selected_hospital_id !== null,422,'This request already has a booking.');
            $quotation = Quotation::firstOrNew(['treatment_request_id'=>$item->id,'hospital_id'=>$request->user()->id]);
            if ($quotation->withdrawn_at) $quotation->last_emailed_fingerprint = null;
            $quotation->fill($data);
            $quotation->withdrawn_at = null;
            $quotation->save();
            $item->update(['status'=>'quotes_received']);
            return $quotation;
        });
        $delivery = app(\App\Services\QuotationEmailNotification::class)->send($quotation);
        return back()->with('status', match ($delivery) {
            'sent' => 'Quotation saved. The customer has been notified by email.',
            'already_sent' => 'Quotation saved. The customer was already notified about this response.',
            'disabled' => 'Quotation saved. The customer has turned off quotation email alerts.',
            default => 'Quotation saved, but the customer email could not be sent. Save the quotation again to retry.',
        });
    }

    public function withdraw(Request $request, Quotation $quotation) {
        abort_unless($request->user()->isHospital(),403);
        abort_unless($quotation->hospital_id === $request->user()->id,404);
        DB::transaction(function () use ($quotation) {
            $item = TreatmentRequest::whereKey($quotation->treatment_request_id)->lockForUpdate()->firstOrFail();
            $quote = Quotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            abort_if($item->booking()->exists() || $item->selected_hospital_id || $item->payment_confirmed_at,422,'A quotation cannot be withdrawn after a hospital has been selected.');
            if (!$quote->withdrawn_at) $quote->update(['withdrawn_at'=>now()]);
            if ($item->status !== 'cancelled') $item->update(['status'=>$item->quotations()->exists()?'quotes_received':'pending']);
        });
        return back()->with('status','Quotation withdrawn. The customer can no longer select it.');
    }

    public function accept(Request $request, Quotation $quotation) {
        $request->validate(['expected_total_satang'=>['nullable','integer','min:1']]);
        $booking = DB::transaction(function () use ($request,$quotation) {
            $item = TreatmentRequest::where('user_id',$request->user()->id)->lockForUpdate()->findOrFail($quotation->treatment_request_id);
            $quote = Quotation::whereKey($quotation->id)->whereNull('withdrawn_at')->lockForUpdate()->firstOrFail();
            abort_unless($quote->hospital?->isHospital(),422);
            abort_if($item->status==='cancelled' || $item->payment_confirmed_at || $item->selected_hospital_id || $item->selected_hospital_name || ($quote->valid_until && $quote->valid_until->endOfDay()->isPast()),422,'This quotation cannot be selected.');
            if ($request->filled('expected_total_satang')) {
                [$whole,$fraction] = explode('.', $quote->amount);
                abort_unless(((int)$whole*100+(int)$fraction)===(int)$request->input('expected_total_satang'),422,'The quotation price has changed. Please reload and review it again.');
            }
            $existing = Booking::where('treatment_request_id',$item->id)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->status==='Awaiting Acceptance' && $existing->deposit_status==='Pending'
                    && $existing->quotation_id===$quote->id && $existing->hospital_id===$quote->hospital_id
                    && $existing->user_id===$request->user()->id,422,'This booking cannot be accepted again.');
            }
            [$whole,$fraction] = explode('.', $quote->amount);
            $due = \App\Services\DepositPayment::calculate((int)$whole*100+(int)$fraction);
            $item->update(['status'=>'pending_payment','selected_hospital_id'=>$quote->hospital_id,'selected_hospital_name'=>$quote->hospital->name,'hospital_approved_at'=>now()]);
            if ($existing) {
                $existing->update(['status'=>'Pending Payment','deposit_amount_satang'=>$existing->deposit_amount_satang ?? $due]);
                (new \App\Services\PaymentLedger)->ensureCurrent($existing);
                return $existing;
            }
            $created = Booking::create(['treatment_request_id'=>$item->id,'quotation_id'=>$quote->id,'hospital_id'=>$quote->hospital_id,
                'user_id'=>$request->user()->id,'status'=>'Pending Payment','deposit_status'=>'Pending','deposit_amount_satang'=>$due,'is_test'=>false]);
            (new \App\Services\PaymentLedger)->ensureCurrent($created);
            return $created;
        });
        if ($request->expectsJson()) return response()->json((new \App\Services\DepositPayment)->summary($booking),201)->header('Cache-Control','private, no-store');
        return back()->with('status','Quotation accepted. Booking is pending verified deposit payment.');
    }

    public function treatmentDeposit(Request $request, Booking $booking) {
        $request->validate(['confirm_treatment'=>'required|accepted']);
        $row = DB::transaction(function() use ($request,$booking) {
            $item = TreatmentRequest::whereKey($booking->treatment_request_id)->lockForUpdate()->firstOrFail();
            $row = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->user_id === $request->user()->id && $item->user_id === $request->user()->id,404);
            abort_unless($item->status !== 'cancelled' && !in_array($row->status,['Cancelled','Completed']) && !$row->is_test && !$row->payment_is_simulated,422);
            if ($row->deposit_phase === 'treatment') {
                (new \App\Services\PaymentLedger)->ensureCurrent($row);
                return $row;
            }
            abort_unless($row->deposit_status === 'Paid' && $row->payment_verified_at && $item->payment_confirmed_at && $item->selected_hospital_id === $row->hospital_id && $item->hospital_approved_at,422,'A verified initial payment and selected hospital are required.');
            $summary = (new \App\Services\DepositPayment)->treatmentSummary($row);
            abort_unless($summary['additional_satang'] > 0,422,'No additional deposit is due. Contact support.');
            if (!$row->paymentSubmissions()->where('purpose','initial')->where('status','approved')->exists()) {
                $row->paymentSubmissions()->create(['purpose'=>'initial','amount_satang'=>$summary['initial_satang'],'method'=>'Legacy verified payment','status'=>'approved','submitted_at'=>$row->payment_verified_at,'reviewed_at'=>$row->payment_verified_at,'reviewer_id'=>$row->payment_verified_by]);
            }
            $row->update(['deposit_phase'=>'treatment','deposit_percentage'=>$summary['percentage'],'deposit_amount_satang'=>$summary['additional_satang'],'deposit_status'=>'Pending','status'=>'Pending Payment','payment_verified_at'=>null,'payment_verified_by'=>null]);
            (new \App\Services\PaymentLedger)->ensureCurrent($row);
            $item->update(['status'=>'pending_booking_deposit']);
            return $row->fresh();
        });
        return response()->json((new \App\Services\DepositPayment)->summary($row));
    }

    public function payment(Request $request, Booking $booking) {
        abort_unless($booking->user_id===$request->user()->id && $booking->is_payment_eligible && $booking->status!=='Awaiting Acceptance',404);
        return response()->json((new \App\Services\DepositPayment)->summary($booking))->header('Cache-Control','private, no-store');
    }

    public function complete(Request $request, Booking $booking) {
        abort_unless($request->user()->isHospital(),403);
        abort_unless($booking->hospital_id===$request->user()->id,404);
        abort_unless($booking->status==='Confirmed' && $booking->deposit_status==='Paid',422,'The deposit must be paid before completion.');
        $booking->update(['status'=>'Completed']);
        return back()->with('status','Booking completed.');
    }
}
