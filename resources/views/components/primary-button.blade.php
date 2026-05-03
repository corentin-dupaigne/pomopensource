<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-white/20 border border-white/40 rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-white/30 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-0 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
