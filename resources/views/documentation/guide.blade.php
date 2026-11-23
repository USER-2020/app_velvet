@php($secondsPerStep = 9)
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $flow['title'] }} · Velvet Master</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #171020; color: #fdf7ff; font-family: system-ui, sans-serif; }
        .composition {
            position: relative;
            width: 1280px;
            height: 720px;
            overflow: hidden;
            background: radial-gradient(ellipse at top right, #412254, transparent 65%), #171020;
        }
        .clip {
            position: absolute;
            inset: 0;
            display: grid;
            align-content: center;
            padding: 80px 100px;
        }
        .module { margin: 0; color: #ecd3f7; font-size: 25px; font-weight: 650; }
        .step-number { margin: 32px 0 0; color: #eab0f6; font-size: 23px; }
        h1 { margin: 12px 0 20px; font: 52px/1.08 Georgia, serif; overflow-wrap: anywhere; }
        .description { max-width: 990px; margin: 0; color: #ede2f2; font-size: 29px; line-height: 1.5; }
        .progress { display: flex; gap: 10px; margin-top: 32px; }
        .progress span { width: 35px; height: 6px; border-radius: 6px; background: #675073; }
        .progress .reached { background: #eab0f6; }
        @if ($preview)
        body { display: flex; height: 100vh; flex-direction: column; overflow: hidden; }
        .composition { width: 100%; height: auto; min-height: 0; flex: 1; overflow-y: auto; }
        .clip { position: relative; min-height: 100%; padding: clamp(20px, 5vw, 40px); }
        .clip[hidden] { display: none; }
        .module { font-size: clamp(15px, 2.4vw, 22px); }
        .step-number { margin-top: 14px; font-size: 13px; }
        h1 { margin: 6px 0 12px; font-size: clamp(22px, 4vw, 38px); }
        .description { font-size: clamp(15px, 2.4vw, 20px); }
        .progress { margin-top: 18px; }
        .progress span { width: 22px; height: 4px; }
        .controls { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; padding: 8px; background: #241831; }
        button { min-height: 40px; border: 1px solid #ac78bf; border-radius: 8px; background: #392748; color: #fff; padding: 4px 9px; font: inherit; font-size: 13px; cursor: pointer; }
        button:disabled { opacity: .6; cursor: default; }
        button:focus-visible { outline: 2px solid #efd8fb; outline-offset: 2px; }
        @endif
    </style>
</head>
<body>
    <main class="composition" data-composition-id="velvet-{{ $flow['key'] }}" data-start="0" data-duration="{{ count($flow['steps']) * $secondsPerStep }}" data-width="1280" data-height="720" data-no-timeline>
        @foreach ($flow['steps'] as $step)
            <section class="clip" id="step-{{ $loop->iteration }}" data-start="{{ $loop->index * $secondsPerStep }}" data-duration="{{ $secondsPerStep }}" data-track-index="1" @if ($preview && !$loop->first) hidden @endif>
                <p class="module">{{ $flow['title'] }}</p>
                <p class="step-number">Paso {{ $loop->iteration }} de {{ $loop->count }}</p>
                <h1>{{ $step['title'] }}</h1>
                <p class="description">{{ $step['description'] }}</p>
                <div class="progress" aria-hidden="true">
                    @for ($i = 0; $i < $loop->count; $i++)
                        <span @class(['reached' => $i <= $loop->index])></span>
                    @endfor
                </div>
            </section>
        @endforeach
    </main>
    @if ($preview)
        <div class="controls" aria-label="Controles de la demostración">
            <button type="button" id="previous" disabled>Anterior</button>
            <button type="button" id="play">Reproducir</button>
            <button type="button" id="next">Siguiente</button>
        </div>
        <script>
            const clips = [...document.querySelectorAll('.clip')];
            const composition = document.querySelector('.composition');
            const previous = document.getElementById('previous');
            const next = document.getElementById('next');
            const play = document.getElementById('play');
            let current = 0;
            let timer = null;

            function show(index) {
                current = index;
                clips.forEach((clip, position) => { clip.hidden = position !== current; });
                composition.scrollTop = 0;
                previous.disabled = current === 0;
                next.disabled = current === clips.length - 1;
            }

            function stop() {
                window.clearTimeout(timer);
                timer = null;
                play.textContent = current === clips.length - 1 ? 'Repetir' : 'Reproducir';
            }

            function scheduleNext() {
                timer = window.setTimeout(() => {
                    if (current === clips.length - 1) {
                        stop();
                        return;
                    }
                    show(current + 1);
                    scheduleNext();
                }, Number(clips[current].dataset.duration) * 1000);
            }

            previous.addEventListener('click', () => { show(current - 1); stop(); });
            next.addEventListener('click', () => { show(current + 1); stop(); });
            play.addEventListener('click', () => {
                if (timer !== null) {
                    stop();
                    return;
                }
                if (current === clips.length - 1) show(0);
                play.textContent = 'Pausar';
                scheduleNext();
            });
            show(0);
        </script>
    @endif
</body>
</html>
