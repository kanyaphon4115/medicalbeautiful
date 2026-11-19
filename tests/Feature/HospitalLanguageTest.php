<?php

namespace Tests\Feature;

use App\Models\{HospitalProfile, Quotation, TreatmentRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_hospital_dashboard_profile_and_templates_render_both_languages_without_changing_private_data(): void
    {
        $hospital = User::factory()->create(['role'=>'hospital','name'=>'Patient Requests']);
        $owner = User::factory()->create();
        $item = TreatmentRequest::create(['reference'=>fake()->uuid(),'user_id'=>$owner->id,'name'=>'Dashboard','email'=>$owner->email,'procedure'=>'Eye Surgery','goals'=>'New Requests','preferred_surgery_month'=>12,'preferred_surgery_year'=>2026,'status'=>'pending','consented_at'=>now(),'consent_text'=>'Consent']);
        Quotation::create(['treatment_request_id'=>$item->id,'hospital_id'=>$hospital->id,'amount'=>5000,'details'=>'Pending Payment','valid_until'=>today()->addDays(5)]);
        HospitalProfile::create(['hospital_id'=>$hospital->id,'name'=>'Patient Requests','short_description'=>'Dashboard']);
        $this->actingAs($hospital);
        foreach (['th','ko'] as $locale) {
            $this->postJson(route('website.language'), ['language'=>$locale])->assertOk();
            $response = $this->get(route('hospital.dashboard'))->assertOk()->assertSee('<html lang="'.$locale.'">',false);
            foreach (['Patient Requests','New Requests','Quotations Sent','Recent Patient Requests','Submit quotation','Withdraw Quotation','No bookings yet.'] as $key) {
                $this->assertNotSame($key, __($key, [], $locale));
                $response->assertSee(__($key, [], $locale));
            }
            $response->assertSee('data-user-content>Pending Payment',false)
                ->assertSee('data-user-content>New Requests',false)
                ->assertSee('data-language-switcher',false);
            $response->assertSee(\Carbon\Carbon::create(2026,12,1)->locale($locale)->translatedFormat('F Y'))
                ->assertDontSee('December 2026');
            $this->get(route('hospital.profile'))->assertOk()->assertSee(__('Hospital Profile', [], $locale))
                ->assertSee(__('Hospital Information', [], $locale))->assertSee(__('Contact Information', [], $locale))
                ->assertSee('data-user-content>Dashboard',false);
        }
        $this->assertSame('Pending Payment', Quotation::first()->details);
        $this->assertSame('New Requests', $item->fresh()->goals);
        $this->assertSame('Patient Requests', $hospital->fresh()->name);
    }

    public function test_hospital_validation_translates_field_names_and_preserves_validation_rules(): void
    {
        $hospital=User::factory()->create(['role'=>'hospital']);
        $owner=User::factory()->create();
        $item=TreatmentRequest::create(['reference'=>fake()->uuid(),'user_id'=>$owner->id,'name'=>$owner->name,'email'=>$owner->email,'procedure'=>'Eye Surgery','status'=>'pending','consented_at'=>now(),'consent_text'=>'Consent']);
        $this->actingAs($hospital);
        foreach (['th','ko'] as $locale) {
            $this->postJson(route('website.language'), ['language'=>$locale])->assertOk();
            $this->postJson(route('hospital.profile.update'), [])->assertUnprocessable()
                ->assertJsonValidationErrors(['name','address','country','phone','email'])
                ->assertJsonPath('errors.address.0',fn($message)=>str_contains($message,__('Hospital Address',[],$locale)));
            $this->postJson(route('hospital.quote',$item), [])->assertUnprocessable()
                ->assertJsonValidationErrors(['amount','details','valid_until'])
                ->assertJsonPath('errors.amount.0',fn($message)=>str_contains($message,__('Amount',[],$locale)));
        }
        $this->assertDatabaseCount('hospital_profiles',0);
    }
}
