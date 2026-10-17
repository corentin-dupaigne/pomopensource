{{-- Signing in with Discord gives the same account as the Discord Activity. --}}
@if (app(\App\Services\Discord::class)->configured())
    <div class="mb-6">
        <a href="{{ route('discord.redirect') }}"
           class="flex w-full items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-[#5865F2] hover:bg-[#4752C4] font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-white transition">
            <i class="fab fa-discord text-base" aria-hidden="true"></i>
            {{ __('Continue with Discord') }}
        </a>
        <p class="mt-2 text-xs text-center text-white/60">
            {{ __('Same account and data as in the Discord Activity.') }}
        </p>
        <x-input-error :messages="$errors->get('discord')" class="mt-2" />

        <div class="flex items-center gap-3 mt-6 text-xs uppercase tracking-widest text-white/50" aria-hidden="true">
            <span class="h-px flex-1 bg-white/20"></span>
            {{ __('or') }}
            <span class="h-px flex-1 bg-white/20"></span>
        </div>
    </div>
@endif
