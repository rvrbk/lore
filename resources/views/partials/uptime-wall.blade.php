<div class="overflow-x-auto" role="img" aria-label="Uptime per day over the last year">
    <div class="flex w-max gap-0.5">
        @foreach($wall as $week)
            <div class="flex flex-col gap-0.5">
                @foreach($week as $day)
                    @if($day === null)<span class="size-2.5"></span>@else<span class="size-2.5 {{ \App\Services\UptimeWall::colour($day['percent']) }}" title="{{ $day['day'] }}: {{ $day['percent'] === null ? 'no data' : $day['percent'].'% up' }}"></span>@endif
                @endforeach
            </div>
        @endforeach
    </div>
</div>
