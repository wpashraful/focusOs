<template>
    <div class="auth-shell">
        <div class="auth-card">
            <!-- Logo -->
            <div class="auth-logo">
                <div class="auth-logo__icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="url(#g)" stroke-width="1.5"/>
                        <circle cx="12" cy="12" r="5" fill="url(#g)"/>
                        <defs>
                            <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#6366f1"/>
                                <stop offset="100%" style="stop-color:#8b5cf6"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <span class="auth-logo__name">FocusOS</span>
            </div>

            <slot />
        </div>

        <!-- Background orbs -->
        <div class="orb orb--1" />
        <div class="orb orb--2" />
    </div>
</template>

<style scoped>
.auth-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0b0c10;
    position: relative;
    overflow: hidden;
    padding: 20px;
}

.auth-card {
    width: 100%;
    max-width: 400px;
    background: #0f1117;
    border: 1px solid #1e2130;
    border-radius: 16px;
    padding: 36px 32px;
    position: relative;
    z-index: 1;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(99,102,241,0.05);
}

.auth-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-bottom: 28px;
}

.auth-logo__icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.auth-logo__name {
    font-size: 1.4rem;
    font-weight: 700;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Background decoration */
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
}
.orb--1 {
    width: 400px; height: 400px;
    background: rgba(99, 102, 241, 0.08);
    top: -100px; left: -100px;
}
.orb--2 {
    width: 300px; height: 300px;
    background: rgba(139, 92, 246, 0.06);
    bottom: -80px; right: -80px;
}
</style>
