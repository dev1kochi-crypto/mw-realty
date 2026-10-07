{{--
    Module help guide — content from resources/help/portal/{topic}.php (App\Support\PortalHelp).
    A short slide tour: Welcome → What you can do → How it works → Good to know (empty ones skipped).
    Opens from the top bar's "!" button, and by itself ~2.5s into the first visit of each module.
    Portal users: "already shown" is saved on their account; a Super Admin viewing the portal: this browser.
--}}
@php
    $helpSeenAlready = $owner
        ? in_array($helpTopic, $owner->seen_help_topics ?? [], true)
        : null; // decided client-side (localStorage)
    $helpSlides = array_values(array_filter([
        'welcome',
        !empty($help['features']) ? 'features' : null,
        !empty($help['steps']) ? 'steps' : null,
        !empty($help['tips']) ? 'tips' : null,
    ]));
@endphp
<div class="modal fade portal-tour" id="portalHelpModal" tabindex="-1" aria-labelledby="portalHelpTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close portal-tour__close" data-bs-dismiss="modal" aria-label="Close"></button>

            <div class="portal-tour__slides">
                @foreach($helpSlides as $slide)
                <section class="portal-tour__slide {{ $loop->first ? 'is-active' : '' }}" data-slide="{{ $loop->index }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                    @if($slide === 'welcome')
                        <div class="portal-tour__hero">
                            <span class="portal-tour__ring portal-tour__ring--1"></span>
                            <span class="portal-tour__ring portal-tour__ring--2"></span>
                            <span class="portal-tour__hero-icon"><i class="fas {{ $help['icon'] ?? 'fa-circle-info' }}"></i></span>
                        </div>
                        <div class="portal-tour__welcome">
                            <div class="portal-tour__eyebrow">Quick guide</div>
                            <h5 class="portal-tour__title" id="portalHelpTitle">Welcome to {{ $help['title'] }}</h5>
                            @if(!empty($help['intro']))
                            <p class="portal-tour__lead">{{ $help['intro'] }}</p>
                            @endif
                            <div class="portal-tour__meta">
                                <i class="fas fa-clock"></i> {{ count($helpSlides) - 1 }} short {{ count($helpSlides) - 1 === 1 ? 'step' : 'steps' }} &middot; under a minute
                            </div>
                        </div>
                    @else
                        @php
                            [$slideIcon, $slideTitle] = match ($slide) {
                                'features' => ['fa-wand-magic-sparkles', 'What you can do here'],
                                'steps' => ['fa-route', 'How it works'],
                                'tips' => ['fa-lightbulb', 'Good to know'],
                            };
                        @endphp
                        <div class="portal-tour__head">
                            <span class="portal-tour__head-icon portal-tour__head-icon--{{ $slide }}"><i class="fas {{ $slideIcon }}"></i></span>
                            <div>
                                <div class="portal-tour__eyebrow">{{ $help['title'] }}</div>
                                <h6 class="portal-tour__head-title">{{ $slideTitle }}</h6>
                            </div>
                        </div>
                        <div class="portal-tour__body">
                            @if($slide === 'features')
                                <ul class="portal-tour__features">
                                    @foreach($help['features'] as $feature)
                                    <li>
                                        <span class="portal-tour__feature-icon"><i class="fas {{ $feature['icon'] ?? 'fa-check' }}"></i></span>
                                        <div>
                                            <div class="portal-tour__item-title">{{ $feature['title'] }}</div>
                                            <div class="portal-tour__item-text">{{ $feature['text'] }}</div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            @elseif($slide === 'steps')
                                <ol class="portal-tour__steps">
                                    @foreach($help['steps'] as $step)
                                    <li>
                                        <span class="portal-tour__step-num">{{ $loop->iteration }}</span>
                                        <div>
                                            <div class="portal-tour__item-title">{{ $step['title'] }}</div>
                                            <div class="portal-tour__item-text">{{ $step['text'] }}</div>
                                        </div>
                                    </li>
                                    @endforeach
                                </ol>
                            @else
                                <ul class="portal-tour__tips">
                                    @foreach($help['tips'] as $tip)
                                    <li><i class="fas fa-circle-check"></i><span>{{ $tip }}</span></li>
                                    @endforeach
                                </ul>
                                <div class="portal-tour__reopen">
                                    <span class="portal-tour__reopen-icon"><i class="fas fa-circle-exclamation"></i></span>
                                    <span>Need this again? Click the <strong>!</strong> icon at the top of the page anytime.</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>
                @endforeach
            </div>

            <div class="portal-tour__foot">
                <button type="button" class="portal-tour__skip" data-bs-dismiss="modal">Skip</button>
                <div class="portal-tour__dots" role="tablist" aria-label="Guide steps">
                    @foreach($helpSlides as $slide)
                    <button type="button" class="portal-tour__dot {{ $loop->first ? 'is-active' : '' }}" data-go="{{ $loop->index }}" aria-label="Go to step {{ $loop->iteration }}"></button>
                    @endforeach
                </div>
                <div class="portal-tour__nav">
                    <button type="button" class="portal-tour__back" data-tour-back aria-label="Back"><i class="fas fa-arrow-left"></i></button>
                    <button type="button" class="portal-tour__next" data-tour-next>
                        <span data-tour-next-label>Let's go</span> <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .portal-help-btn { position: relative; border: none; background: #f4f6fb; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #4b5065; font-size: 1.1rem; }
    .portal-help-btn:hover { background: #e9ecf6; color: var(--portal-primary); }
    .portal-help-btn.is-new { color: var(--portal-primary); }
    .portal-help-btn.is-new::after { content: ''; position: absolute; inset: -3px; border-radius: 50%; border: 2px solid var(--portal-primary); animation: portalHelpPulse 1.6s ease-out infinite; }
    @keyframes portalHelpPulse { 0% { opacity: .9; transform: scale(.9); } 100% { opacity: 0; transform: scale(1.35); } }

    .portal-tour .modal-dialog { max-width: 540px; }
    .portal-tour .modal-content { border: 0; border-radius: 24px; overflow: hidden; box-shadow: 0 30px 70px rgba(20, 20, 43, .28); }
    .portal-tour__close { position: absolute; top: 14px; right: 14px; z-index: 3; width: 32px; height: 32px; padding: 0; border-radius: 50%; background-color: rgba(255, 255, 255, .85); background-size: 10px; opacity: 1; }
    .portal-tour__close:hover { background-color: #fff; }

    /* Slides: stacked in one grid cell so the card keeps the height of the tallest one — no jumping. */
    .portal-tour__slides { display: grid; }
    .portal-tour__slide { grid-area: 1 / 1; display: flex; flex-direction: column; min-width: 0; opacity: 0; visibility: hidden; transform: translateX(24px); transition: opacity .3s ease, transform .3s ease, visibility 0s linear .3s; }
    .portal-tour__slide.is-active { opacity: 1; visibility: visible; transform: none; transition: opacity .3s ease, transform .3s ease; }
    .portal-tour__slide.is-left { transform: translateX(-24px); }

    .portal-tour__hero { position: relative; height: 190px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: radial-gradient(circle at 25% 20%, rgba(255, 255, 255, .18), transparent 45%), linear-gradient(135deg, var(--portal-primary), var(--portal-primary-dark)); }
    .portal-tour__ring { position: absolute; border-radius: 50%; border: 1px solid rgba(255, 255, 255, .18); }
    .portal-tour__ring--1 { width: 170px; height: 170px; }
    .portal-tour__ring--2 { width: 270px; height: 270px; border-color: rgba(255, 255, 255, .1); }
    .portal-tour__hero-icon { position: relative; width: 84px; height: 84px; border-radius: 26px; display: flex; align-items: center; justify-content: center; font-size: 2.1rem; color: var(--portal-primary); background: #fff; box-shadow: 0 16px 34px rgba(0, 0, 0, .22); transform: rotate(-6deg); }
    .portal-tour__slide.is-active .portal-tour__hero-icon { animation: portalTourPop .55s cubic-bezier(.34, 1.56, .64, 1) both; }
    @keyframes portalTourPop { from { transform: rotate(-6deg) scale(.6); opacity: 0; } to { transform: rotate(-6deg) scale(1); opacity: 1; } }

    .portal-tour__welcome { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.6rem 2rem 1.2rem; text-align: center; }
    .portal-tour__eyebrow { text-transform: uppercase; letter-spacing: .09em; font-size: .68rem; font-weight: 800; color: var(--portal-muted); }
    .portal-tour__title { font-weight: 800; font-size: 1.4rem; color: var(--portal-text); margin: .3rem 0 .55rem; }
    .portal-tour__lead { margin: 0 auto; max-width: 420px; font-size: .92rem; color: var(--portal-muted); line-height: 1.6; }
    .portal-tour__meta { display: inline-flex; align-items: center; gap: .4rem; margin-top: 1rem; padding: .3rem .8rem; border-radius: 999px; background: #f2f4fa; font-size: .75rem; font-weight: 700; color: var(--portal-muted); }

    .portal-tour__head { display: flex; align-items: center; gap: .85rem; padding: 1.5rem 3.5rem 1rem 1.75rem; }
    .portal-tour__head-icon { width: 46px; height: 46px; flex-shrink: 0; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; }
    .portal-tour__head-icon--features { background: linear-gradient(135deg, var(--portal-primary), #4a6fa8); }
    .portal-tour__head-icon--steps { background: linear-gradient(135deg, #0f9d58, #34c880); }
    .portal-tour__head-icon--tips { background: linear-gradient(135deg, #e08e0b, #f5b942); }
    .portal-tour__head-title { margin: .1rem 0 0; font-weight: 800; font-size: 1.15rem; color: var(--portal-text); }
    .portal-tour__body { padding: 0 1.75rem 1.25rem; max-height: min(52vh, 430px); overflow-y: auto; scrollbar-width: thin; }

    .portal-tour__item-title { font-weight: 700; font-size: .87rem; color: var(--portal-text); }
    .portal-tour__item-text { font-size: .8rem; color: var(--portal-muted); line-height: 1.5; margin-top: .1rem; }

    .portal-tour__features { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .5rem; }
    .portal-tour__features li { display: flex; gap: .75rem; padding: .7rem .8rem; border-radius: 14px; background: #f7f8fc; }
    .portal-tour__feature-icon { width: 32px; height: 32px; flex-shrink: 0; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #fff; color: var(--portal-primary); font-size: .82rem; box-shadow: 0 2px 6px rgba(20, 20, 43, .06); }

    .portal-tour__steps { list-style: none; margin: 0; padding: 0; }
    .portal-tour__steps li { display: flex; gap: .85rem; position: relative; padding-bottom: 1rem; }
    .portal-tour__steps li:last-child { padding-bottom: 0; }
    .portal-tour__steps li:not(:last-child)::before { content: ''; position: absolute; left: 15px; top: 34px; bottom: 4px; width: 2px; border-radius: 2px; background: #d9f1e4; }
    .portal-tour__step-num { width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .82rem; color: #0f9d58; background: #e3f6ec; border: 2px solid #bfe9d2; }
    .portal-tour__steps .portal-tour__item-title { padding-top: .35rem; }

    .portal-tour__tips { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .55rem; }
    .portal-tour__tips li { display: flex; gap: .65rem; padding: .7rem .85rem; border-radius: 14px; background: #fff8e8; font-size: .83rem; color: #5c4a1c; line-height: 1.5; }
    .portal-tour__tips li i { color: #e0a106; margin-top: .2rem; }
    .portal-tour__reopen { display: flex; align-items: center; gap: .7rem; margin-top: 1rem; padding: .75rem .85rem; border-radius: 14px; border: 1px dashed #c9d3e6; font-size: .8rem; color: var(--portal-muted); }
    .portal-tour__reopen-icon { width: 30px; height: 30px; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #f4f6fb; color: var(--portal-primary); }

    .portal-tour__foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .9rem 1.25rem .9rem 1.5rem; border-top: 1px solid var(--portal-border); background: #fcfcfe; }
    .portal-tour__skip { border: 0; background: none; padding: .3rem 0; font-size: .82rem; font-weight: 700; color: var(--portal-muted); min-width: 60px; text-align: left; }
    .portal-tour__skip:hover { color: var(--portal-text); }
    .portal-tour__skip.is-hidden { visibility: hidden; }
    .portal-tour__dots { display: flex; gap: .4rem; }
    .portal-tour__dot { width: 8px; height: 8px; padding: 0; border: 0; border-radius: 999px; background: #d5d9e6; transition: width .25s ease, background .25s ease; }
    .portal-tour__dot.is-active { width: 24px; background: var(--portal-primary); }
    .portal-tour__dot.is-done { background: #9fb1cf; }
    .portal-tour__nav { display: flex; align-items: center; gap: .4rem; }
    .portal-tour__back { width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--portal-border); background: #fff; color: var(--portal-muted); font-size: .8rem; }
    .portal-tour__back:hover { color: var(--portal-primary); border-color: #c9d3e6; }
    .portal-tour__back.is-hidden { visibility: hidden; }
    .portal-tour__next { display: inline-flex; align-items: center; gap: .45rem; height: 38px; padding: 0 1.1rem; border: 0; border-radius: 999px; background: var(--portal-primary); color: #fff; font-weight: 700; font-size: .85rem; box-shadow: 0 6px 14px rgba(36, 67, 115, .25); transition: background .15s ease, transform .15s ease; }
    .portal-tour__next:hover { background: var(--portal-primary-dark); transform: translateX(2px); }
    .portal-tour__next.is-finish { background: #0f9d58; box-shadow: 0 6px 14px rgba(15, 157, 88, .25); }

    @media (max-width: 575.98px) {
        .portal-tour .modal-dialog { margin: .75rem; }
        .portal-tour__hero { height: 150px; }
        .portal-tour__welcome { padding: 1.3rem 1.25rem 1rem; }
        .portal-tour__head { padding: 1.25rem 3.25rem .85rem 1.25rem; }
        .portal-tour__body { padding: 0 1.25rem 1rem; }
        .portal-tour__foot { padding: .8rem 1rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .portal-tour__slide, .portal-tour__slide.is-active { transition: none; }
        .portal-tour__slide.is-active .portal-tour__hero-icon { animation: none; }
    }
</style>

<script>
(function () {
    const el = document.getElementById('portalHelpModal');
    const btn = document.getElementById('portalHelpBtn');
    if (!el || !btn) return;
    const topic = @json($helpTopic);
    const storageKey = 'portalHelpSeen:' + topic;
    const serverSeen = @json($helpSeenAlready);
    const seenUrl = @json($owner ? route('portal.help.seen', $helpTopic) : null);
    const modal = bootstrap.Modal.getOrCreateInstance(el);

    // --- Slide tour ---
    const slides = Array.from(el.querySelectorAll('.portal-tour__slide'));
    const dots = Array.from(el.querySelectorAll('.portal-tour__dot'));
    const backBtn = el.querySelector('[data-tour-back]');
    const nextBtn = el.querySelector('[data-tour-next]');
    const nextLabel = el.querySelector('[data-tour-next-label]');
    const skipBtn = el.querySelector('.portal-tour__skip');
    let current = 0;

    function go(index) {
        index = Math.max(0, Math.min(slides.length - 1, index));
        slides.forEach(function (s, i) {
            s.classList.toggle('is-active', i === index);
            s.classList.toggle('is-left', i < index);
            s.setAttribute('aria-hidden', i === index ? 'false' : 'true');
        });
        dots.forEach(function (d, i) {
            d.classList.toggle('is-active', i === index);
            d.classList.toggle('is-done', i < index);
        });
        const last = index === slides.length - 1;
        backBtn.classList.toggle('is-hidden', index === 0);
        skipBtn.classList.toggle('is-hidden', last);
        nextBtn.classList.toggle('is-finish', last);
        nextLabel.textContent = last ? 'Got it' : (index === 0 ? "Let's go" : 'Next');
        const body = slides[index].querySelector('.portal-tour__body');
        if (body) body.scrollTop = 0;
        current = index;
    }

    nextBtn.addEventListener('click', function () {
        if (current === slides.length - 1) modal.hide(); else go(current + 1);
    });
    backBtn.addEventListener('click', function () { go(current - 1); });
    dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.go); }); });
    el.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); nextBtn.click(); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); go(current - 1); }
    });
    el.addEventListener('show.bs.modal', function () { go(0); });
    go(0);

    btn.addEventListener('click', function () { btn.classList.remove('is-new'); modal.show(); });

    // --- First visit to this module: open the tour after a moment, once ---
    // Seen = saved on the account, or remembered by this browser (a fallback if that save ever fails).
    let seen = serverSeen === true;
    try { seen = seen || localStorage.getItem(storageKey) === '1'; } catch (e) { seen = seen || serverSeen === null; }
    if (seen) return;

    btn.classList.add('is-new');
    setTimeout(function () {
        if (document.querySelector('.modal.show')) return; // don't stack on another open dialog — try next visit
        modal.show();
        btn.classList.remove('is-new');
        try { localStorage.setItem(storageKey, '1'); } catch (e) {}
        if (seenUrl) {
            fetch(seenUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } })
                .catch(function () {});
        }
    }, 2500);
})();
</script>
