<template>
    <div class="stat-card" :class="`stat-card--${color}`">
        <div class="stat-card__icon">{{ icon }}</div>
        <div class="stat-card__body">
            <div class="stat-card__value">
                {{ value }}<span v-if="suffix" class="stat-card__suffix">{{ suffix }}</span>
            </div>
            <div class="stat-card__label">{{ label }}</div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    icon: String,
    label: String,
    value: { type: [Number, String], default: 0 },
    suffix: String,
    color: { type: String, default: 'indigo' },
});
</script>

<style scoped>
.stat-card {
    background: #0f1117;
    border: 1px solid #1e2130;
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: border-color 0.2s, transform 0.15s;
}
.stat-card:hover { transform: translateY(-1px); }

.stat-card__icon { font-size: 1.6rem; flex-shrink: 0; }

.stat-card__value {
    font-size: 1.6rem;
    font-weight: 700;
    line-height: 1;
    color: #e2e8f0;
}
.stat-card__suffix { font-size: 0.9rem; font-weight: 400; color: #4a5568; }
.stat-card__label { font-size: 0.72rem; color: #64748b; margin-top: 3px; }

/* Color variants — left border accent */
.stat-card--green  { border-left: 3px solid #10b981; }
.stat-card--indigo { border-left: 3px solid #6366f1; }
.stat-card--purple { border-left: 3px solid #8b5cf6; }
.stat-card--orange { border-left: 3px solid #f59e0b; }
</style>
