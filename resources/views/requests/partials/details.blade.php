<div class="request-record-details" data-no-translate lang="en">
    <dl>
        <dt>Full Name</dt><dd>{{ trim((string)$item->name) ?: (trim((string)$item->customer?->name) ?: 'Name not provided') }}</dd>
        <dt>Email Address</dt><dd>{{ $item->email }}</dd>
        <dt>LINE ID</dt><dd>{{ $item->line_id ?: 'Not provided' }}</dd>
        <dt>Phone Number</dt><dd>{{ $item->phone_number ?: 'Not provided' }}</dd>
        <dt>Date of Birth</dt><dd>{{ $item->date_of_birth ?: 'Not provided' }}</dd><dt>Age</dt><dd>{{ $item->age ?? 'Not provided' }}</dd>
        <dt>Gender</dt><dd>{{ $item->gender ?: 'Not provided' }}</dd>
        <dt>Country / Region</dt><dd>{{ $item->customer?->country_code ?: 'Not provided' }}</dd>
        <dt>Preferred Surgery Month / Year</dt><dd>{{ $item->preferred_period_label }}</dd>
    </dl>
    <h3>Selected Procedures</h3><ul>@foreach($item->procedure_names as $procedure)<li>{{ $procedure }}</li>@endforeach</ul>
    <h3>Your Beauty Goals</h3><p class="request-sensitive-text">{{ $item->goals ?: 'Not provided' }}</p>
    <h3>Additional Notes</h3><p class="request-sensitive-text">{{ $item->additional_notes ?: 'Not provided' }}</p><h3>Medical Information</h3><p class="request-sensitive-text">{{ $item->medical_information ?: 'Not provided' }}</p>
    <h3>Uploaded Photos</h3><div class="request-private-photos">
        @forelse($item->photos as $photo)<a href="{{ route('requests.photos.show',$photo) }}" target="_blank" rel="noopener"><img src="{{ route('requests.photos.show',$photo) }}" alt="Private submitted photo" loading="lazy"></a>@empty<p>No photos uploaded.</p>@endforelse
    </div>
</div>
