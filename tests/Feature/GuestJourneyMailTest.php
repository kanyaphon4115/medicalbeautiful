<?php

namespace Tests\Feature;

use App\Notifications\GuestRequestAccess;
use App\Notifications\GuestJourneyCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class GuestJourneyMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setDate(2026,10,27));
    }

    private function payload(): array
    {
        return ['name'=>'Guest Example','email'=>'guest@example.test','phone_number'=>'+66812345678',
            'line_id'=>'guest-line','age'=>25,'gender'=>'Prefer not to say','procedures'=>['Eye Surgery','Rhinoplasty'],
            'goals'=>'Example private goals','medical_information'=>'Example private medical notes',
            'preferred_surgery_month'=>12,'preferred_surgery_year'=>2027,'consent_to_share'=>1,
            'hospitals'=>['aesthetic-hospital-bangkok']];
    }

    private function submitAndAccess(): string
    {
        $id=$this->postJson(route('guest-journey.mail.store'),$this->payload())->assertCreated()->json('token');
        $this->get(URL::temporarySignedRoute('guest-journey.mail.access',now()->addDays(7),['guest'=>$id]))->assertRedirect(route('home').'#journey/email/'.$id);
        return $id;
    }

    public function test_real_mail_notification_has_private_link_and_month_year_only(): void
    {
        $response=$this->postJson(route('guest-journey.mail.store'),$this->payload())->assertCreated();
        $id=$response->json('token');
        Notification::assertSentOnDemand(GuestRequestAccess::class,function($notification,$channels,$recipient) use($id) {
            $this->assertStringContainsString($id,$notification->url);
            $this->assertSame('December 2027',$notification->period);
            $this->assertSame('guest@example.test',$recipient->routes['mail']);
            return true;
        });
        $this->assertStringNotContainsString('private medical',Cache::get('guest-journey:'.$id));
        $response->assertJsonMissingPath('url')->assertJsonMissingPath('code');
        $this->getJson(route('guest-journey.mail.show',$id))->assertForbidden();
        $this->get(route('guest-journey.mail.access',$id))->assertForbidden();
    }

    public function test_signed_email_access_restores_request_in_another_session(): void
    {
        $id=$this->submitAndAccess();
        $this->getJson(route('guest-journey.mail.show',$id))->assertOk()->assertJsonPath('date','2027-12')->assertJsonPath('procedures',['Eye Surgery','Rhinoplasty'])->assertJsonMissingPath('contact');
        $this->flushSession();
        $this->getJson(route('guest-journey.mail.show',$id))->assertForbidden();
        $this->get(URL::temporarySignedRoute('guest-journey.mail.access',now()->addDays(7),['guest'=>$id]))->assertRedirect();
        $this->getJson(route('guest-journey.mail.show',$id))->assertOk();
        $this->travel(8)->days();
        $this->getJson(route('guest-journey.mail.show',$id))->assertForbidden();
    }

    public function test_code_is_emailed_not_returned_and_can_only_be_used_once(): void
    {
        $id=$this->submitAndAccess();
        $this->postJson(route('guest-journey.mail.code',$id))->assertOk()->assertJsonMissingPath('code');
        $code=null;
        Notification::assertSentOnDemand(GuestJourneyCode::class,function($notification) use(&$code){$code=$notification->code;return true;});
        $this->assertNotNull($code);
        $this->assertArrayNotHasKey('code',Cache::get('guest-journey-code:'.$id));
        $this->postJson(route('guest-journey.mail.code',$id))->assertTooManyRequests();
        $this->postJson(route('guest-journey.mail.verify',$id),['code'=>$code])->assertOk()->assertJsonPath('verified',true);
        $this->postJson(route('guest-journey.mail.verify',$id),['code'=>$code])->assertUnprocessable();
    }

    public function test_code_expires_and_attempts_and_access_are_limited(): void
    {
        $id=$this->submitAndAccess();
        $this->postJson(route('guest-journey.mail.code',$id))->assertOk();
        $code=null;
        Notification::assertSentOnDemand(GuestJourneyCode::class,function($n)use(&$code){$code=$n->code;return true;});
        $this->travel(6)->minutes();
        $this->postJson(route('guest-journey.mail.verify',$id),['code'=>$code])->assertUnprocessable();
        for($i=0;$i<4;$i++) $this->postJson(route('guest-journey.mail.verify',$id),['code'=>'123456'])->assertUnprocessable();
        $this->postJson(route('guest-journey.mail.verify',$id),['code'=>'123456'])->assertTooManyRequests();
        $this->flushSession();
        $this->postJson(route('guest-journey.mail.code',$id))->assertForbidden();
    }

    public function test_invalid_procedures_past_period_and_missing_consent_are_rejected(): void
    {
        $this->postJson(route('guest-journey.mail.store'),array_replace($this->payload(),['procedures'=>[]]))->assertUnprocessable();
        $this->postJson(route('guest-journey.mail.store'),array_replace($this->payload(),['preferred_surgery_month'=>1,'preferred_surgery_year'=>2026]))->assertUnprocessable();
        $this->postJson(route('guest-journey.mail.store'),array_replace($this->payload(),['consent_to_share'=>0]))->assertUnprocessable();
        Notification::assertNothingSent();
    }
}
