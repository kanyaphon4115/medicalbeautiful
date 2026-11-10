<?php

namespace Tests\Feature;

use App\Models\{User, TreatmentRequest, Quotation};
use App\Notifications\HospitalQuotationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Notification, URL};
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Notification::fake();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function item(?User $owner = null): TreatmentRequest {
        return TreatmentRequest::create([
            'reference'=>(string) Str::uuid(), 'user_id'=>$owner?->id,
            'name'=>'PRIVATE-NAME', 'email'=>'request@example.test', 'procedure'=>'Rhinoplasty',
            'goals'=>'SECRET-GOALS', 'medical_information'=>'SECRET-MEDICAL',
            'status'=>'pending', 'consented_at'=>now(), 'consent_text'=>'Consent',
        ]);
    }

    private function payload(): array {
        return ['amount'=>70000, 'details'=>'SECRET-QUOTATION', 'valid_until'=>today()->addDays(5)->format('Y-m-d')];
    }

    public function test_hospital_submission_emails_request_address_with_private_expiring_guest_link(): void {
        $item=$this->item();$hospital=User::factory()->create(['role'=>'hospital']);$url=null;
        $this->actingAs($hospital)->post(route('hospital.quote',$item),$this->payload())
            ->assertRedirect()->assertSessionHas('status','Quotation saved. The customer has been notified by email.');
        Notification::assertSentOnDemand(HospitalQuotationReceived::class,function($notification,$channels,$recipient)use($item,&$url){
            $this->assertSame('request@example.test',$recipient->routes['mail']);
            $this->assertTrue($notification->guest);
            $this->assertSame(sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id),$notification->reference);
            $mail=$notification->toMail($recipient);$this->assertSame('View Quotation',$mail->actionText);
            $content=json_encode($mail->toArray());
            foreach(['PRIVATE-NAME','SECRET-GOALS','SECRET-MEDICAL','SECRET-QUOTATION'] as $secret)$this->assertStringNotContainsString($secret,$content);
            $url=$notification->url;
            $this->assertTrue(URL::hasValidSignature(\Illuminate\Http\Request::create($url)));
            $this->assertStringContainsString($item->reference,$url);
            return true;
        });
        $this->get($url)->assertRedirect(route('guest.requests.show',$item->reference));
        $this->get(route('guest.requests.show',$item->reference))->assertOk()->assertSee('SECRET-QUOTATION')->assertDontSee('Resend access link');
        $this->assertDatabaseCount('users',1);
        $this->travel(7)->days();$this->travel(1)->seconds();
        $this->get($url)->assertForbidden();$this->travelBack();
    }

    public function test_same_response_is_not_emailed_twice_but_updated_and_other_hospital_quotes_are(): void {
        $item=$this->item();$hospital=User::factory()->create(['role'=>'hospital']);$data=$this->payload();
        $this->actingAs($hospital)->post(route('hospital.quote',$item),$data)->assertRedirect();
        $this->post(route('hospital.quote',$item),$data)->assertRedirect();
        Notification::assertSentOnDemandTimes(HospitalQuotationReceived::class,1);
        $this->post(route('hospital.quote',$item),array_replace($data,['details'=>'UPDATED-SECRET']))->assertRedirect();
        Notification::assertSentOnDemandTimes(HospitalQuotationReceived::class,2);
        $this->actingAs(User::factory()->create(['role'=>'hospital']))->post(route('hospital.quote',$item),$data)->assertRedirect();
        Notification::assertSentOnDemandTimes(HospitalQuotationReceived::class,3);
        $this->assertDatabaseCount('quotations',2);
        $this->assertNotNull(Quotation::first()->customer_emailed_at);
    }

    public function test_mail_failure_keeps_quotation_and_can_retry_same_response(): void {
        $item=$this->item();$hospital=User::factory()->create(['role'=>'hospital']);
        Notification::swap(new class {public function route(...$arguments){throw new \RuntimeException('SECRET-SMTP-DETAIL');}});
        $this->actingAs($hospital)->post(route('hospital.quote',$item),$this->payload())->assertRedirect()
            ->assertSessionHas('status','Quotation saved, but the customer email could not be sent. Save the quotation again to retry.');
        $this->assertDatabaseCount('quotations',1);
        $this->assertNull(Quotation::first()->last_emailed_fingerprint);
        Notification::fake();
        $this->post(route('hospital.quote',$item),$this->payload())->assertRedirect();
        Notification::assertSentOnDemandTimes(HospitalQuotationReceived::class,1);
    }

    public function test_account_preferences_and_authorization_are_respected(): void {
        $owner=User::factory()->create(['notification_preferences'=>['quotations'=>false]]);
        $item=$this->item($owner);$hospital=User::factory()->create(['role'=>'hospital']);
        $this->actingAs($owner)->post(route('hospital.quote',$item),$this->payload())->assertForbidden();
        $this->actingAs($hospital)->post(route('hospital.quote',$item),$this->payload())->assertRedirect();
        Notification::assertNothingSent();
        $owner->notification_preferences=['quotations'=>true];$owner->save();
        $this->post(route('hospital.quote',$item),$this->payload())->assertRedirect();
        Notification::assertSentOnDemand(HospitalQuotationReceived::class,fn($n)=>!$n->guest&&$n->url===route('requests.index'));
    }
}
