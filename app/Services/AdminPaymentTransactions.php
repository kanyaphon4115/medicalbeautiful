<?php
namespace App\Services;

use App\Models\{PaymentSubmission,Booking};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminPaymentTransactions
{
    public function pageData(array $filters, Builder $eligibleBookings): array
    {
        $columns=['id','booking_id','purpose','amount_satang','method','transaction_reference','proof_path','proof_name',
            'status','reviewer_id','reviewed_at','review_notes','submitted_at','created_at','updated_at'];
        $payments=DB::table('payment_submissions')->select($columns)
            ->whereIn('booking_id',(clone $eligibleBookings)->select('bookings.id'));

        // Read-only compatibility for retained bookings with unknown amounts or no transaction yet.
        $legacy=(clone $eligibleBookings)->whereDoesntHave('paymentSubmissions',fn($q)=>$q->whereColumn('purpose','bookings.deposit_phase'))
            ->selectRaw("-CAST(bookings.id AS SIGNED) as id, bookings.id as booking_id, deposit_phase as purpose, deposit_amount_satang as amount_satang,
                'Not submitted' as method, NULL as transaction_reference, NULL as proof_path, NULL as proof_name,
                CASE deposit_status WHEN 'Paid' THEN 'approved' WHEN 'Information Required' THEN 'information_requested'
                    WHEN 'Pending Verification' THEN 'pending' WHEN 'Pending' THEN 'pending' ELSE LOWER(deposit_status) END as status,
                payment_verified_by as reviewer_id, payment_verified_at as reviewed_at, NULL as review_notes,
                bookings.created_at as submitted_at, bookings.created_at, bookings.updated_at");
        $rows=PaymentSubmission::query()->fromSub($payments->unionAll($legacy->toBase()),'payment_submissions');

        if($filters['record']??null)$rows->where('booking_id',$filters['record']);
        if($filters['payment']??null)$rows->whereKey($filters['payment']);
        if($filters['legacy']??null)$rows->whereKey(-$filters['legacy']);
        if($hospital=trim($filters['hospital']??''))$rows->whereHas('booking.hospital',fn($q)=>$q->where('name','like',"%$hospital%"));
        if($method=$filters['method']??null)$rows->where('method',$method==='unrecorded'?'Not submitted':$method);
        if($status=$filters['status']??null){
            $status=match($status){'Paid'=>'approved','Pending','Pending Payment','Pending Verification'=>'pending','Information Required'=>'information_requested',default=>strtolower($status)};
            $rows->where('status',$status);
            if(($filters['status']??null)==='Pending Verification')$rows->whereHas('booking',fn($q)=>$q->where('deposit_status','Pending Verification')->whereColumn('bookings.deposit_phase','payment_submissions.purpose'));
        }
        if($search=trim($filters['search']??''))$rows->where(function($q)use($search){
            $q->whereHas('booking',fn($b)=>$b->whereHas('customer',fn($u)=>$u->where('name','like',"%$search%")->orWhere('email','like',"%$search%"))
                ->orWhereHas('treatmentRequest',fn($r)=>$r->where('name','like',"%$search%")->orWhere('email','like',"%$search%"))
                ->orWhereHas('hospital',fn($h)=>$h->where('name','like',"%$search%")));
            if(preg_match('/^PAY-\d{4}-(L-)?(\d+)$/i',$search,$m))$q->orWhere('payment_submissions.id',($m[1]??'')?-(int)$m[2]:(int)$m[2]);
            elseif(preg_match('/^(BK|REQ)-\d{4}-(\d+)$/i',$search,$m)){
                if(strtoupper($m[1])==='BK')$q->orWhere('booking_id',(int)$m[2]);
                else $q->orWhereHas('booking',fn($b)=>$b->where('treatment_request_id',(int)$m[2]));
            }elseif(ctype_digit($search))$q->orWhere('payment_submissions.id',(int)$search)->orWhere('booking_id',(int)$search);
        });
        $paymentDate="COALESCE(reviewed_at, CASE WHEN method <> 'Not submitted' THEN submitted_at END)";
        if($filters['from']??null)$rows->whereRaw("DATE($paymentDate) >= ?",[$filters['from']]);
        if($filters['to']??null)$rows->whereRaw("DATE($paymentDate) <= ?",[$filters['to']]);

        $counts=['all'=>(clone $rows)->count(),'initial'=>(clone $rows)->where('purpose','initial')->count(),
            'treatment'=>(clone $rows)->where('purpose','treatment')->count()];
        if(($filters['stage']??'all')!=='all')$rows->where('purpose',$filters['stage']);
        $transactions=$rows->with(['booking.customer','booking.hospital','booking.quotation','booking.treatmentRequest',
            'booking.paymentSubmissions','reviews.reviewer','reviewer'])->orderByDesc('created_at')->orderByDesc('id')
            ->paginate((int)($filters['size']??5));
        return ['paymentTransactions'=>$transactions,'paymentStageCounts'=>$counts];
    }
}
