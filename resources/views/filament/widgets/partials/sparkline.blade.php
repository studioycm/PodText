@props([
    'points' => [],
    'testid' => null,
    'stroke' => null,
    'days' => [],
    'label' => null,
])

@php
    $values = array_values(array_map('floatval', $points));
    $count = count($values);
    $min = $count > 0 ? min($values) : 0.0;
    $span = ($count > 0 ? max($values) : 0.0) - $min;
    $step = $count > 1 ? 100 / ($count - 1) : 0;

    // Time runs left to right even inside the RTL board.
    //
    // Normalised over max−min inside the [2, 22] band of the 24-high box —
    // against the peak alone, [8, 9, 10] rendered almost flat. A spanless
    // series (all equal, all zero, or a single point) sits on the midline
    // rather than hugging an edge.
    $polyline = collect($values)
        ->map(fn (float $value, int $index): string => sprintf(
            '%.2f,%.2f',
            $index * $step,
            $span > 0 ? 22 - (($value - $min) / $span) * 20 : 12,
        ))
        ->implode(' ');

    // The hover layer needs exactly one Jerusalem day per point; anything
    // else — no days, a misaligned list, a single point — renders the bare
    // svg so a wrong day can never reach a tooltip. Labels and values are
    // formatted here, in PHP: the browser only ever moves strings around.
    $interactive = $count > 1 && count(array_values($days)) === $count;
    $hoverPoints = $interactive
        ? collect($values)
            ->map(fn (float $value, int $index): array => [
                'x' => sprintf('%.2f', $index * $step),
                'day' => \Illuminate\Support\Carbon::parse(array_values($days)[$index])->format(\App\Support\UiFormats::date()),
                'value' => \App\Support\UiFormats::number($value),
            ])
            ->all()
        : [];
@endphp

@if ($interactive)
    {{-- Local Alpine state only: no wire calls, no persistence, no polling. --}}
    <div
        x-data="{
            active: null,
            point(index) {
                return this.$refs.points.children[index]?.dataset;
            },
            get count() {
                return this.$refs.points.children.length;
            },
            get current() {
                return this.active === null ? null : this.point(this.active);
            },
            get tooltipStyle() {
                const point = this.current;
                if (! point) return 'display: none';
                const x = Number(point.x);
                const shift = x < 15 ? '0' : x > 85 ? '-100%' : '-50%';
                return 'left: ' + x + '%; transform: translateX(' + shift + ')';
            },
            show(index) {
                this.active = Math.min(this.count - 1, Math.max(0, index));
                const point = this.current;
                if (point) {
                    this.$refs.live.textContent = point.day + ' — ' + point.value;
                }
            },
            hide() {
                this.active = null;
                this.$refs.live.textContent = '';
            },
            track(event) {
                const rect = event.currentTarget.getBoundingClientRect();
                if (! rect.width) {
                    return;
                }
                this.show(Math.round(((event.clientX - rect.left) / rect.width) * (this.count - 1)));
            },
        }"
    >
        <div
            {{-- The crosshair walks the same LTR direction as the time axis,
                 so pointer maths and arrow keys stay uniform on the RTL board:
                 ArrowRight always moves toward the later day. --}}
            dir="ltr"
            tabindex="0"
            role="img"
            @if ($label) aria-label="{{ $label }}" @endif
            class="focus-visible:ring-primary-500 dark:focus-visible:ring-primary-400 relative w-full cursor-crosshair rounded-md focus:outline-none focus-visible:ring-2"
            @if ($testid) data-testid="{{ $testid }}-hover" @endif
            x-on:pointermove="track($event)"
            x-on:pointerleave="hide()"
            x-on:focus="show(count - 1)"
            x-on:blur="hide()"
            x-on:keydown.arrow-right.prevent="show(active === null ? count - 1 : active + 1)"
            x-on:keydown.arrow-left.prevent="show(active === null ? count - 1 : active - 1)"
            x-on:keydown.home.prevent="show(0)"
            x-on:keydown.end.prevent="show(count - 1)"
            x-on:keydown.escape.prevent.stop="hide()"
        >
@endif

<svg
    dir="ltr"
    viewBox="0 0 100 24"
    preserveAspectRatio="none"
    class="h-6 w-full"
    aria-hidden="true"
    @if ($testid) data-testid="{{ $testid }}" @endif
>
    @if ($count > 1)
        <polyline
            points="{{ $polyline }}"
            @if ($stroke) class="{{ $stroke }}" @endif
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
            vector-effect="non-scaling-stroke"
        />
    @endif

    @if ($interactive)
        <line
            x-show="active !== null"
            x-bind:x1="current?.x"
            x-bind:x2="current?.x"
            y1="0"
            y2="24"
            style="display: none"
            stroke="currentColor"
            stroke-width="1"
            vector-effect="non-scaling-stroke"
            class="opacity-40"
            @if ($testid) data-testid="{{ $testid }}-crosshair" @endif
        />
    @endif
</svg>

@if ($interactive)
    <div
        x-bind:style="tooltipStyle"
        style="display: none"
        aria-hidden="true"
        class="pointer-events-none absolute bottom-full z-10 mb-1 rounded-md bg-gray-900 px-2 py-1 text-xs font-medium whitespace-nowrap text-white tabular-nums shadow-md dark:bg-white dark:text-gray-950"
        @if ($testid) data-testid="{{ $testid }}-tooltip" @endif
    >
        <span x-text="current?.day"></span>
        <span class="opacity-60">·</span>
        <span x-text="current?.value"></span>
    </div>

    <span x-ref="points" class="hidden" @if ($testid) data-testid="{{ $testid }}-points" @endif>
        @foreach ($hoverPoints as $index => $point)
            <span
                data-index="{{ $index }}"
                data-x="{{ $point['x'] }}"
                data-day="{{ $point['day'] }}"
                data-value="{{ $point['value'] }}"
            ></span>
        @endforeach
    </span>
    </div>

    {{-- Announced as focus or arrows move the crosshair; sits outside the
             role="img" wrapper because that role hides its own subtree. --}}
    <span x-ref="live" aria-live="polite" class="sr-only"></span>
    </div>
@endif
