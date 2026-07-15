<h1>Changes to your listing</h1>

<p>An administrator made changes to <strong>{{ $product->name }}</strong> while reviewing it. Please log in to the seller portal to review and accept these changes before the listing goes live.</p>

<table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
    <thead>
        <tr>
            <th align="left">Field</th>
            <th align="left">Previous Value</th>
            <th align="left">New Value</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($editTrail->changes as $field => $change)
            <tr>
                <td>{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                <td>{{ is_array($change['old']) ? implode(', ', $change['old']) : $change['old'] }}</td>
                <td>{{ is_array($change['new']) ? implode(', ', $change['new']) : $change['new'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<p><a href="{{ route('filament.seller.auth.login') }}">Log in to review and accept</a></p>
