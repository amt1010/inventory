<h1>A seller import appears stuck</h1>
<p>The import of <strong>{{ $import->file_name }}</strong> has processed
{{ $import->processed_rows }} of {{ $import->total_rows }} rows and hasn't
made progress recently.</p>
<p>This usually means the queue-worker service is offline or has stopped
consuming jobs. Check the queue-worker service in Railway, then check
<code>php artisan queue:failed</code> for anything related to this
import.</p>
