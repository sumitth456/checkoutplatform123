<div class="fi-section col-span-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">

    @php

        $endpoints = $overview['endpoints'] ?? [];

    @endphp



    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">

            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Verified gateways</p>

            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $overview['webhook_verified'] ?? 0 }}</p>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $overview['gateways_active'] ?? 0 }} active / {{ $overview['gateways_total'] ?? 0 }} total</p>

        </div>

        <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">

            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Needs attention</p>

            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ ($overview['webhook_failed'] ?? 0) + ($overview['webhook_unknown'] ?? 0) }}</p>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $overview['webhook_failed'] ?? 0 }} failed · {{ $overview['webhook_unknown'] ?? 0 }} unknown</p>

        </div>

        <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">

            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Stripe events (24h)</p>

            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $overview['stripe_events_24h'] ?? 0 }}</p>

            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $overview['stripe_processed_24h'] ?? 0 }} processed · {{ $overview['stripe_failed_24h'] ?? 0 }} failed</p>

        </div>

        <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">

            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Stripe event</p>

            @if (! empty($overview['last_stripe_event_at']))

                <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $overview['last_stripe_event_at']->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</p>

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $overview['last_stripe_event_type'] ?? '—' }}</p>

            @else

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No events recorded yet</p>

            @endif

        </div>

    </div>



    <div class="mt-8 border-t border-gray-200 pt-6 dark:border-white/10">

        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Inbound webhook URLs</h2>

        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">

            Register these URLs with each payment provider. Stripe uses a dedicated ingress; other providers share the generic webhook route.

        </p>



        <ul class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">

            @foreach ($endpoints as $endpoint)

                <li class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">

                    <div>

                        <span class="font-medium text-gray-950 dark:text-white">{{ $endpoint['label'] }}</span>

                        <span class="ml-2 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $endpoint['method'] }}</span>

                    </div>

                    <code class="break-all rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $endpoint['url'] }}</code>

                </li>

            @endforeach

        </ul>

    </div>

</div>

