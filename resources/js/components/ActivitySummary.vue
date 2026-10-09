<template>
  <!-- The figure leads; a small icon and a muted label explain it. Hairline
       dividers group the row without boxing each stat. -->
  <dl class="activity-summary grid grid-cols-3 divide-x divide-white/10 rounded-xl bg-white/10 backdrop-blur-lg py-5 short:py-3">
    <div v-for="stat in items" :key="stat.label" class="flex flex-col-reverse items-center gap-1 px-2 text-center">
      <dt class="flex items-center gap-1.5 text-sm text-white/60">
        <i :class="[stat.icon, stat.iconClass]" class="text-xs" aria-hidden="true"></i>
        {{ stat.label }}
      </dt>
      <dd class="text-white tabular-nums">
        <span class="text-3xl short:text-2xl font-bold">{{ stat.value }}</span>
        <span class="ml-1 text-base short:text-sm font-medium text-white/60">{{ stat.unit }}</span>
      </dd>
    </div>
  </dl>
</template>

<script>
const plural = (count, one, many) => (Number(count) === 1 ? one : many);

export default {
  props: {
    stats: {
      type: Object,
      required: true,
    },
  },
  computed: {
    items() {
      const { hours_focused: hours, days_accessed: days, day_streak: streak } = this.stats;
      return [
        { label: 'Time focused', value: hours, unit: 'h', icon: 'fas fa-clock', iconClass: 'text-white/60' },
        { label: 'Days active', value: days, unit: plural(days, 'day', 'days'), icon: 'fas fa-calendar-alt', iconClass: 'text-white/60' },
        // The one accent in the row: the streak is the stat worth keeping up.
        { label: 'Current streak', value: streak, unit: plural(streak, 'day', 'days'), icon: 'fas fa-fire', iconClass: 'text-orange-400' },
      ];
    },
  },
};
</script>
