<div class="space-y-2">
    @if ($trail)
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th class="text-left">Field</th>
                    <th class="text-left">Previous</th>
                    <th class="text-left">New</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trail->changes as $field => $change)
                    <tr>
                        <td>{{ ucfirst(str_replace('_', ' ', $field)) }}</td>
                        <td>{{ is_array($change['old']) ? implode(', ', $change['old']) : $change['old'] }}</td>
                        <td>{{ is_array($change['new']) ? implode(', ', $change['new']) : $change['new'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No pending changes found.</p>
    @endif
</div>
