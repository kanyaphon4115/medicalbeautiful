<?php
namespace Tests\Feature;

use App\Models\{User,TreatmentRequest,Quotation,Booking};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HospitalWorkflowTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_quotations_are_available_only_inside_request_details(): void {
        $customer=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
        $item=$this->requestFor($customer);
        Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>60000,'details'=>'Preserved hospital quotation','valid_until'=>today()->addDays(5)]);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->get('/admin/quotations')->assertNotFound();
        $this->get('/admin/requests')->assertOk()->assertSee('Hospital Quotations')->assertSee('Preserved hospital quotation')->assertSee('60,000.00')->assertDontSee('href="'.url('/admin/quotations'),false);
        $this->assertDatabaseCount('quotations',1);
    }
    private function requestFor(User $customer, array $extra=[]): TreatmentRequest {
        return TreatmentRequest::create(array_merge(['reference'=>(string)Str::uuid(),'user_id'=>$customer->id,
            'name'=>$customer->name,'email'=>$customer->email,'procedure'=>'Rhinoplasty','goals'=>'Real treatment goal',
            'status'=>'pending','consented_at'=>now(),'consent_text'=>'Consent'],$extra));
    }
    public function test_shared_requests_and_hospital_quotes_become_a_private_booking(): void {
        $customer=User::factory()->create(['country_code'=>'TH']);
        $h1=User::factory()->create(['role'=>'hospital']); $h2=User::factory()->create(['role'=>'hospital']);
        $item=$this->requestFor($customer);
        foreach([$h1,$h2] as $h) $this->actingAs($h)->get('/hospital/dashboard')->assertOk()->assertSee('Real treatment goal');
        $data=['amount'=>12000,'details'=>'Hospital one offer','valid_until'=>today()->addDays(10)->format('Y-m-d')];
        $this->actingAs($h1)->post(route('hospital.quote',$item),$data)->assertRedirect();
        $this->post(route('hospital.quote',$item),$data)->assertRedirect();
        $this->assertDatabaseCount('quotations',1);
        $this->assertSame('quotes_received',$item->fresh()->status);
        $this->actingAs($h2)->get('/hospital/dashboard')->assertDontSee('Hospital one offer');
        $this->post(route('hospital.quote',$item),array_replace($data,['details'=>'Hospital two offer']))->assertRedirect();
        $quote=Quotation::where('hospital_id',$h1->id)->firstOrFail();
        $this->actingAs($customer)->get('/my-requests')->assertOk()->assertSee('Hospital one offer');
        $this->post(route('quotations.accept',$quote))->assertRedirect();
        $this->post(route('quotations.accept',$quote))->assertUnprocessable();
        $this->assertDatabaseCount('bookings',1);
        $booking=Booking::firstOrFail();
        $this->assertSame('Pending Payment',$booking->status); $this->assertSame('Pending',$booking->deposit_status);
        $this->actingAs($h2)->get('/hospital/dashboard')->assertDontSee('Real treatment goal')->assertDontSee('Hospital one offer');
        $this->post(route('hospital.quote',$item),$data)->assertNotFound();
        $this->post(route('hospital.complete',$booking))->assertNotFound();
        $this->actingAs($h1)->post(route('hospital.complete',$booking))->assertUnprocessable();
        $booking->update(['deposit_status'=>'Paid','status'=>'Confirmed','appointment_date'=>today()->addDay()]);
        $this->post(route('hospital.complete',$booking))->assertRedirect();
        $this->assertSame('Completed',$booking->fresh()->status);
        $this->get('/hospital/dashboard')->assertViewHas('stats',fn($s)=>$s['Completed Bookings']===1 && $s['Quotations Sent']===1);
    }
    public function test_exclusive_legacy_cancelled_and_unauthorized_requests_are_hidden(): void {
        $customer=User::factory()->create();$hospital=User::factory()->create(['role'=>'hospital']);
        $other=User::factory()->create(['role'=>'hospital']);
        $private=$this->requestFor($customer,['selected_hospital_id'=>$other->id,'goals'=>'Private other hospital']);
        $this->requestFor($customer,['selected_hospital_name'=>'Unmapped legacy clinic','goals'=>'Private legacy request']);
        $this->requestFor($customer,['status'=>'cancelled','goals'=>'Cancelled request secret']);
        $this->actingAs($hospital)->get('/hospital/dashboard')->assertOk()->assertDontSee('Private other hospital')->assertDontSee('Private legacy request')->assertDontSee('Cancelled request secret');
        $this->post(route('hospital.quote',$private),['amount'=>100,'details'=>'No','valid_until'=>today()->format('Y-m-d')])->assertNotFound();
        $this->actingAs($customer)->post(route('hospital.quote',$private),[])->assertForbidden();
    }
}
