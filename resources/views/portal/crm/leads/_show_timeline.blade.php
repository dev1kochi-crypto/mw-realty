{{-- Lead page → Timeline tab: Recent activity / Notes / Source history (see LeadDetailService). --}}
<div class="portal-card p-3 p-md-4">
    <ul class="nav nav-pills portal-lead-page-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#leadTabActivity" type="button" role="tab">Recent activity <span class="badge">{{ $timeline->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leadTabNotes" type="button" role="tab">Notes <span class="badge" id="leadNotesCount">{{ $notes->count() }}</span></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leadTabSources" type="button" role="tab">Source history <span class="badge">{{ $sourceHistory->count() }}</span></button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="leadTabActivity" role="tabpanel">
            <ol class="portal-lead-timeline">
                @foreach($timeline as $entry)
                <li class="portal-lead-timeline-item tone-{{ $entry['tone'] }}">
                    <span class="portal-lead-timeline-icon"><i class="fas {{ $entry['icon'] }}" aria-hidden="true"></i></span>
                    <div class="portal-lead-timeline-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>{{ $entry['title'] }}</strong>
                            <span class="text-muted small" title="{{ $entry['at']->format('d M Y, H:i') }}">{{ $entry['at']->format('d M Y, H:i') }} · {{ $entry['at']->diffForHumans() }}</span>
                        </div>
                        @if($entry['body'])<div class="portal-lead-timeline-text">{{ $entry['body'] }}</div>@endif
                        @if($entry['author'])<div class="text-muted small mt-1">by {{ $entry['author'] }}</div>@endif
                    </div>
                </li>
                @endforeach
            </ol>
        </div>

        <div class="tab-pane fade" id="leadTabNotes" role="tabpanel">
            <form id="leadNoteForm" class="mb-3" novalidate>
                <label class="form-label fw-semibold" for="leadNoteBody">Add a note</label>
                <textarea id="leadNoteBody" name="body" class="form-control" rows="3" maxlength="5000" placeholder="Call summary, follow-up, requirements…"></textarea>
                <div class="invalid-feedback" id="leadNoteError"></div>
                <div class="text-end mt-2">
                    <button type="submit" class="btn btn-portal-primary btn-sm" id="leadNoteSave">
                        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span><i class="fas fa-plus me-1"></i> Add note
                    </button>
                </div>
            </form>
            <div id="leadNotesList" class="d-grid gap-2">
                @forelse($notes as $note)
                <div class="portal-lead-page-note">
                    <div class="portal-lead-timeline-text">{{ $note->body }}</div>
                    <div class="text-muted small mt-1">{{ $note->author_name ?: 'Someone' }} · {{ $note->created_at->format('d M Y, H:i') }}</div>
                </div>
                @empty
                <div class="text-muted small" data-empty>No notes yet.</div>
                @endforelse
            </div>
        </div>

        <div class="tab-pane fade" id="leadTabSources" role="tabpanel">
            <p class="text-muted small">Every enquiry this person has sent — the first one created the lead; later ones under the same email or phone were added to it.</p>
            <ol class="portal-lead-timeline">
                @foreach($sourceHistory->reverse() as $enquiry)
                <li class="portal-lead-timeline-item {{ $enquiry['first'] ? 'tone-accent' : 'tone-warning' }}">
                    <span class="portal-lead-timeline-icon"><i class="fas {{ $enquiry['first'] ? 'fa-inbox' : 'fa-envelope-open-text' }}" aria-hidden="true"></i></span>
                    <div class="portal-lead-timeline-body">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>{{ $enquiry['channel'] }}{{ $enquiry['first'] ? ' · first enquiry' : '' }}</strong>
                            <span class="text-muted small">{{ $enquiry['at']->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="small mt-1 d-flex flex-wrap gap-3 text-muted">
                            @if($enquiry['source'])<span><i class="fas fa-share-nodes me-1"></i>{{ $enquiry['source'] }}</span>@endif
                            @if($enquiry['property'])<span><i class="fas fa-building me-1"></i>{{ $enquiry['property']->getTranslation('title') }}</span>@endif
                            @if($enquiry['contact'])<span><i class="fas fa-address-card me-1"></i>{{ $enquiry['contact'] }}</span>@endif
                        </div>
                        @if($enquiry['message'])<div class="portal-lead-timeline-text mt-2">{{ $enquiry['message'] }}</div>@endif
                        @if($enquiry['page_url'])<div class="small mt-1 text-truncate"><a href="{{ $enquiry['page_url'] }}" target="_blank" rel="noopener">{{ $enquiry['page_url'] }}</a></div>@endif
                    </div>
                </li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
