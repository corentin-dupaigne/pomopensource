<template>
  <div class="activity-summary bg-white/10 backdrop-blur-lg rounded-lg p-6 shadow-lg">
    <div class="grid grid-cols-3 gap-4">
      <div class="stat-card text-center p-4 rounded-lg bg-white/10 border border-white/20 shadow-md">
        <i class="fas fa-clock text-3xl mb-3 text-white"></i>
        <div>
          <p class="text-3xl font-bold text-white">{{ stats.hours_focused }}</p>
          <h3 class="text-sm font-medium text-white/70">hours focused</h3>
        </div>
      </div>
      <div class="stat-card text-center p-4 rounded-lg bg-white/10 border border-white/20 shadow-md">
        <i class="fas fa-calendar-alt text-3xl mb-3 text-white"></i>
        <div>
          <p class="text-3xl font-bold text-white">{{ stats.days_accessed }}</p>
          <h3 class="text-sm font-medium text-white/70">days accessed</h3>
        </div>
      </div>
      <div class="stat-card text-center p-4 rounded-lg bg-white/10 border border-white/20 shadow-md">
        <i class="fas fa-fire text-3xl mb-3 text-white"></i>
        <div>
          <p class="text-3xl font-bold text-white">{{ stats.day_streak }}</p>
          <h3 class="text-sm font-medium text-white/70">day streak</h3>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    stats: {
      type: Object,
      required: true,
    },
  },
};
</script>

<style scoped>
.activity-summary {
  background-color: rgba(255, 255, 255, 0.1);
  padding: 1.5rem;
  border-radius: 0.75rem;
  backdrop-filter: blur(10px);
}

.stat-card {
  background-color: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  border-radius: 0.75rem;
  padding: 1rem;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.stat-card i {
  color: white;
}

.stat-card p {
  font-size: 1.875rem; /* 3xl */
  color: white;
}

.stat-card h3 {
  font-size: 0.875rem; /* sm */
  font-weight: 500;
  color: rgba(255, 255, 255, 0.7);
}

/* Short frames: icon beside the figure, so the three fit in one slim row. */
@media (max-height: 500px) {
  .activity-summary {
    padding: 0.5rem;
  }

  .grid {
    gap: 0.5rem;
  }

  .stat-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    text-align: left;
  }

  .stat-card i {
    font-size: 1.25rem;
    margin: 0;
  }

  .stat-card p {
    font-size: 1.25rem;
    line-height: 1.2;
  }

  .stat-card h3 {
    font-size: 0.75rem;
    line-height: 1.2;
  }
}
</style>
