@php
 $countryNames=['TH'=>'Thailand','KR'=>'Korea','VN'=>'Vietnam','TW'=>'Taiwan','JP'=>'Japan','CN'=>'China','US'=>'United States','GB'=>'United Kingdom','AU'=>'Australia','SG'=>'Singapore','MY'=>'Malaysia'];
 $countryLabel=fn($code)=>$code?($countryNames[strtoupper($code)]??strtoupper($code)):'Not recorded';
 $statusLabel=fn($user)=>$user->account_deletion_requested_at?'Deletion requested':($user->email_verified_at?'Active':'Pending verification');
@endphp
<section class="ap-panel" data-real-customers>
 <form method="GET" class="ap-filters"><input type="hidden" name="lang" value="{{ $locale }}">
  <label class="ap-search"><span data-ap-icon="search"></span><input name="search" type="search" value="{{ $customerFilters['search']??'' }}" placeholder="Search by name, ID or email..." aria-label="Search customers"></label>
  <select name="country" aria-label="Country"><option value="">All Countries</option>@foreach($customerCountries as $code)<option value="{{ $code }}" @selected(($customerFilters['country']??'')===$code)>{{ $countryLabel($code) }}</option>@endforeach<option value="unrecorded" @selected(($customerFilters['country']??'')==='unrecorded')>Not recorded</option></select>
  <select name="status" aria-label="Account status"><option value="">All Status</option>@foreach(['active'=>'Active','pending'=>'Pending verification','deletion_requested'=>'Deletion requested'] as $value=>$label)<option value="{{ $value }}" @selected(($customerFilters['status']??'')===$value)>{{ $label }}</option>@endforeach</select>
  <button class="ap-button gold">Apply filters</button><a class="ap-button" href="{{ route('admin.customers',['lang'=>$locale]) }}">Clear</a>
 </form>
 @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
 <div class="ap-table-scroll"><table><thead><tr>@foreach(['Customer','Email','Country','Joined Date','Requests','Bookings','Status','Actions'] as $column)<th>{{ $catalog[$column]??$column }}</th>@endforeach</tr></thead><tbody>
 @forelse($customers as $customer)<tr>
  <td><div class="ap-customer-identity"><span data-user-content class="ap-avatar">{{ mb_substr($customer->name,0,1) }}</span><span><strong data-user-content>{{ $customer->name }}</strong><small>{{ sprintf('C-%03d',$customer->id) }}</small></span></div></td>
  <td data-user-content>{{ $customer->email }}</td><td>{{ __($countryLabel($customer->country_code)) }}</td><td>{{ $customer->created_at?->locale(app()->getLocale())->isoFormat('ll')??__('Not recorded') }}</td>
  <td>{{ $customer->treatment_requests_count }}</td><td>{{ $customer->bookings_count }}</td><td><span class="ap-badge {{ $customer->email_verified_at&&!$customer->account_deletion_requested_at?'green':'gold' }}">{{ __($statusLabel($customer)) }}</span></td>
  <td><button type="button" class="ap-button" data-real-customer="{{ $customer->id }}">{{ $catalog['View']??'View' }}</button></td>
 </tr>@empty<tr><td colspan="8">No customers found.</td></tr>@endforelse
 </tbody></table></div>
 <div class="ap-pagination"><span>Showing {{ $customers->firstItem()??0 }}–{{ $customers->lastItem()??0 }} of {{ $customers->total() }} customers</span>{{ $customers->links() }}</div>
</section>
@foreach($customers as $customer)
 <dialog class="real-request-dialog" id="real-customer-{{ $customer->id }}" aria-labelledby="real-customer-title-{{ $customer->id }}"><button type="button" class="ap-button" data-close-real-customer>Close</button><h2 id="real-customer-title-{{ $customer->id }}">Customer Profile</h2>
  <dl class="request-contact-details"><dt>Customer ID</dt><dd>{{ sprintf('C-%03d',$customer->id) }}</dd><dt>Full Name</dt><dd data-user-content>{{ $customer->name }}</dd><dt>Email</dt><dd data-user-content>{{ $customer->email }}</dd><dt>Phone</dt><dd data-user-content>{{ $customer->phone_number?:__('Not recorded') }}</dd><dt>Country</dt><dd>{{ __($countryLabel($customer->country_code)) }}</dd><dt>Joined Date</dt><dd>{{ $customer->created_at?->locale(app()->getLocale())->isoFormat('ll')??__('Not recorded') }}</dd><dt>Status</dt><dd>{{ __($statusLabel($customer)) }}</dd><dt>Requests</dt><dd>{{ $customer->treatment_requests_count }}</dd><dt>Bookings</dt><dd>{{ $customer->bookings_count }}</dd></dl>
  <h3>Latest requests</h3>@forelse($customer->treatmentRequests as $item)<p class="ap-info-line">{{ sprintf('REQ-%s-%06d',$item->created_at->format('Y'),$item->id) }} · {{ $item->procedure_label }} · {{ $item->status }}</p>@empty<p>No requests recorded.</p>@endforelse
  <h3>Latest bookings</h3>@forelse($customer->bookings as $booking)<p class="ap-info-line">{{ $booking->reference }} · {{ $booking->status }} · {{ $booking->deposit_status }} <a class="ap-button" href="{{ route('admin.bookings',['record'=>$booking->id]) }}">View Booking</a></p>@empty<p>No bookings recorded.</p>@endforelse
 </dialog>
@endforeach
