<div class="request-record-details">
    <dl>
        <dt>{{ __('Full Name') }}</dt><dd data-user-content>{{ trim((string)$item->name) ?: (trim((string)$item->customer?->name) ?: 'Name not provided') }}</dd>
        <dt>{{ __('Email Address') }}</dt><dd data-user-content>{{ $item->email }}</dd>
        <dt>{{ __('LINE ID') }}</dt><dd data-user-content>{{ $item->line_id ?: 'Not provided' }}</dd>
        <dt>{{ __('Phone Number') }}</dt><dd data-user-content>{{ $item->phone_number ?: 'Not provided' }}</dd>
        <dt>{{ __('Date of Birth') }}</dt><dd data-user-content>{{ $item->date_of_birth ?: 'Not provided' }}</dd><dt>{{ __('Age') }}</dt><dd>{{ $item->age ?? 'Not provided' }}</dd>
        <dt>{{ __('Gender') }}</dt><dd>{{ $item->gender ?: 'Not provided' }}</dd>
        <dt>{{ __('Country / Region') }}</dt><dd>{{ $item->customer?->country_code ?: 'Not provided' }}</dd>
        <dt>{{ __('Preferred Surgery Month / Year') }}</dt><dd>{{ $item->preferred_period ? \Carbon\Carbon::createFromFormat('!Y-m',$item->preferred_period)->locale(app()->getLocale())->translatedFormat('F Y') : __('Not specified') }}</dd>
    </dl>
    <h3>{{ __('Selected Procedures') }}</h3><ul>@foreach($item->procedure_names as $procedure)<li>{{ $procedure }}</li>@endforeach</ul>
    <h3>{{ __('Your Beauty Goals') }}</h3><p class="request-sensitive-text">{{ $item->goals ?: 'Not provided' }}</p>
    <h3>{{ __('Additional Notes') }}</h3><p class="request-sensitive-text">{{ $item->additional_notes ?: 'Not provided' }}</p><h3>{{ __('Medical Information') }}</h3><p class="request-sensitive-text">{{ $item->medical_information ?: 'Not provided' }}</p>
    <h3>{{ __('Uploaded Photos') }}</h3><div class="request-private-photos">
        @forelse($item->photos as $photo)<a href="{{ route('requests.photos.show',$photo) }}" target="_blank" rel="noopener"><img src="{{ route('requests.photos.show',$photo) }}" alt="Private submitted photo" loading="lazy"></a>@empty<p>No photos uploaded.</p>@endforelse
    </div>
</div>
