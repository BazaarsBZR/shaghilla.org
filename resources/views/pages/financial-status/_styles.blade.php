.financial-status-shell {
    --fs-ink: #0b2833;
    --fs-muted: #6f8087;
    --fs-paper: #edf3f0;
    --fs-card: #fff;
    --fs-green: #0a8b61;
    --fs-low: #2aad72;
    --fs-mid: #e2b12e;
    --fs-high: #ed7a32;
    --fs-critical: #d52d37;
    --fs-line: #d6e1de;
    margin: 0;
    color: var(--fs-ink);
    background:
        radial-gradient(circle at 4% 10%, rgba(10,139,97,.14), transparent 28rem),
        var(--fs-paper);
    font-family: "Noto Kufi Arabic", "Noto Sans Arabic", Tahoma, sans-serif;
}
.fs-wrap { width: min(1200px, calc(100% - 30px)); margin-inline: auto; }
.fs-hero {
    position: relative;
    overflow: hidden;
    padding: 62px 0 185px;
    color: white;
    background:
        radial-gradient(circle at 14% 20%, rgba(44,190,137,.22), transparent 25rem),
        linear-gradient(120deg, #092c38, #0d4e50 64%, #126745);
}
.fs-hero::after {
    position: absolute;
    inset: 0;
    opacity: .12;
    background-image: radial-gradient(circle, #fff 1px, transparent 1px);
    background-size: 26px 26px;
    content: "";
}
.fs-hero-inner { position: relative; z-index: 1; }
.fs-kicker { margin: 0 0 12px; color: #71d8ad; font-size: .9rem; font-weight: 900; }
.fs-hero h1 { margin: 0; font-size: clamp(2.2rem, 6vw, 5rem); line-height: 1.1; }
.fs-hero p { max-width: 750px; margin: 18px 0 0; color: #d4e8e4; font-size: 1.05rem; line-height: 1.9; }
.fs-dashboard { position: relative; z-index: 2; margin-top: -135px; padding-bottom: 78px; }
.fs-meter-panel {
    display: grid;
    grid-template-columns: minmax(320px, .9fr) 1.1fr;
    gap: 28px;
    padding: 34px;
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 30px;
    background: rgba(255,255,255,.97);
    box-shadow: 0 24px 70px rgba(7,39,48,.16);
}
.fs-gauge-box { text-align: center; }
.fs-gauge-box h2 { margin: 0 0 20px; font-size: 1.35rem; }
.fs-gauge {
    --fs-active: var(--fs-low);
    --fs-active-soft: #e7f7ef;
    position: relative;
    width: min(330px, 78vw);
    aspect-ratio: 1;
    margin: auto;
    border-radius: 50%;
    background: conic-gradient(var(--fs-low) 0 25%, var(--fs-mid) 25% 50%, var(--fs-high) 50% 75%, var(--fs-critical) 75% 100%);
    box-shadow: inset 0 0 0 1px rgba(0,0,0,.04), 0 22px 42px rgba(7,39,48,.15);
}
.fs-gauge.is-mid { --fs-active: var(--fs-mid); --fs-active-soft: #fff7dc; }
.fs-gauge.is-high { --fs-active: var(--fs-high); --fs-active-soft: #fff0e6; }
.fs-gauge.is-critical { --fs-active: var(--fs-critical); --fs-active-soft: #fdebed; }
.fs-gauge::before {
    position: absolute;
    inset: 34px;
    border-radius: 50%;
    background: radial-gradient(circle at 50% 34%, #fff 0 45%, var(--fs-active-soft) 100%);
    box-shadow: inset 0 0 0 1px rgba(17,49,59,.12), 0 0 0 7px rgba(255,255,255,.3);
    content: "";
}
.fs-gauge-needle {
    position: absolute;
    z-index: 2;
    top: 50%;
    right: 50%;
    width: 40%;
    height: 9px;
    background: linear-gradient(90deg, var(--fs-active), var(--fs-ink) 74%);
    clip-path: polygon(0 50%, 100% 0, 100% 100%);
    transform: rotate(calc((var(--score, 0) * 3.6deg) - 270deg));
    transform-origin: right center;
    transition: transform .8s ease;
    filter: drop-shadow(0 3px 3px rgba(12,40,49,.24));
}
.fs-gauge-needle::after { position: absolute; top: -6px; right: -10px; width: 20px; height: 20px; border: 5px solid #fff; border-radius: 50%; background: var(--fs-active); box-shadow: 0 0 0 3px var(--fs-ink), 0 5px 12px rgba(12,40,49,.22); content: ""; }
.fs-gauge-value { position: absolute; z-index: 3; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 0; }
.fs-gauge-value strong { color: var(--fs-active); font-size: 3.5rem; line-height: 1; }
.fs-gauge-value span { margin-top: 9px; padding: 5px 15px; border-radius: 999px; color: var(--fs-ink); background: var(--fs-active-soft); font-size: 1rem; font-weight: 900; line-height: 1.4; letter-spacing: 0; }
.fs-gauge.is-empty { filter: grayscale(1); opacity: .52; }
.fs-zones { display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px; margin-top: 17px; color: var(--fs-muted); font-size: .72rem; }
.fs-zones b { display: block; color: var(--fs-ink); }
.fs-meter-copy { display: flex; flex-direction: column; justify-content: center; }
.fs-period { color: var(--fs-green); font-weight: 900; }
.fs-trend { display: flex; align-items: baseline; gap: 10px; margin: 9px 0 17px; }
.fs-trend strong { font-size: clamp(1.7rem, 4vw, 2.8rem); }
.fs-trend span { color: var(--fs-muted); }
.fs-explanation { margin: 0; padding: 18px; border-right: 4px solid var(--fs-green); border-radius: 12px; line-height: 1.9; background: #f2f8f5; }
.fs-disclaimer { margin: 18px 0; color: var(--fs-muted); font-size: .84rem; line-height: 1.8; }
.fs-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: fit-content;
    min-height: 46px;
    padding: 0 20px;
    border: 1px solid var(--fs-green);
    border-radius: 12px;
    color: var(--fs-green);
    background: transparent;
    font: inherit;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
}
.fs-freshness { display: flex; justify-content: space-between; gap: 15px; margin: 20px 0; padding: 13px 17px; border: 1px solid #bad9cc; border-radius: 14px; color: #195e46; background: #f0faf5; font-size: .86rem; }
.fs-freshness.warning { border-color: #e7ce8b; color: #74580e; background: #fff8e5; }
.fs-section { margin-top: 34px; }
.fs-section-head { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin-bottom: 15px; }
.fs-section-head h2 { margin: 0; font-size: clamp(1.45rem, 3vw, 2.15rem); }
.fs-section-head p { margin: 0; color: var(--fs-muted); }
.fs-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.fs-card { min-height: 194px; padding: 23px; border: 1px solid var(--fs-line); border-radius: 21px; background: var(--fs-card); box-shadow: 0 10px 30px rgba(7,39,48,.045); }
.fs-card small { color: var(--fs-muted); font-weight: 800; }
.fs-card h3 { margin: 8px 0; font-size: 1.2rem; }
.fs-card strong { display: block; margin: 18px 0 8px; color: var(--fs-green); font-size: clamp(1.45rem, 3vw, 2.05rem); direction: ltr; text-align: right; overflow-wrap: anywhere; }
.fs-card p { margin: 0; color: var(--fs-muted); font-size: .82rem; line-height: 1.65; }
.fs-card.is-balance strong.negative { color: var(--fs-critical); }
.fs-insights { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.fs-panel { padding: 25px; border: 1px solid var(--fs-line); border-radius: 23px; background: white; box-shadow: 0 10px 30px rgba(7,39,48,.045); }
.fs-panel h3 { margin: 0 0 7px; font-size: 1.35rem; }
.fs-panel > p { color: var(--fs-muted); line-height: 1.75; }
.fs-killer { margin: 18px 0; padding: 19px; border-radius: 16px; color: white; background: linear-gradient(125deg, #0d6b51, #0b3c45); }
.fs-killer strong { display: block; margin-top: 6px; font-size: 1.45rem; }
.fs-breakdown { display: grid; gap: 13px; margin-top: 22px; }
.fs-break-row { display: grid; grid-template-columns: 128px 1fr 58px; gap: 10px; align-items: center; }
.fs-break-row span { font-size: .86rem; }
.fs-bar { height: 11px; overflow: hidden; border-radius: 99px; background: #e8efed; }
.fs-bar i { display: block; width: var(--width, 0%); height: 100%; border-radius: inherit; background: var(--fs-green); }
.fs-break-row:nth-child(2) .fs-bar i { background: var(--fs-mid); }
.fs-break-row:nth-child(3) .fs-bar i { background: #397eb8; }
.fs-history { display: flex; align-items: end; gap: 18px; min-height: 230px; padding-top: 25px; border-bottom: 1px solid var(--fs-line); }
.fs-history-item { flex: 1; display: grid; grid-template-rows: 1fr auto; align-self: stretch; min-width: 44px; text-align: center; }
.fs-history-bar { align-self: end; width: min(60px, 80%); height: calc(var(--height, 0) * 1%); min-height: 4px; margin: auto; border-radius: 11px 11px 0 0; background: linear-gradient(180deg, var(--fs-critical), var(--fs-high)); }
.fs-history-item span { padding: 8px 0; color: var(--fs-muted); font-size: .78rem; }
.fs-methodology { scroll-margin-top: 24px; }
.fs-methodology summary { cursor: pointer; list-style: none; }
.fs-methodology summary::-webkit-details-marker { display: none; }
.fs-method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px; }
.fs-method { padding: 17px; border-radius: 15px; background: #f1f6f4; }
.fs-method strong { color: var(--fs-green); }
.fs-method p { margin-bottom: 0; color: var(--fs-muted); font-size: .84rem; line-height: 1.7; }
.fs-sources { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
.fs-sources li { display: flex; justify-content: space-between; gap: 16px; padding: 14px 0; border-bottom: 1px solid var(--fs-line); }
.fs-sources li:last-child { border-bottom: 0; }
.fs-sources a { color: var(--fs-green); font-weight: 800; text-decoration: none; }
.fs-empty { padding: 24px; border: 1px dashed #b6c9c4; border-radius: 16px; color: var(--fs-muted); text-align: center; }
@media (max-width: 900px) {
    .fs-meter-panel, .fs-insights { grid-template-columns: 1fr; }
    .fs-cards { grid-template-columns: repeat(2, 1fr); }
    .fs-method-grid { grid-template-columns: 1fr; }
}
@media (max-width: 620px) {
    .fs-wrap { width: min(100% - 20px, 1200px); }
    .fs-hero { padding-top: 38px; }
    .fs-meter-panel { padding: 22px 16px; border-radius: 22px; }
    .fs-cards { grid-template-columns: 1fr; }
    .fs-section-head, .fs-freshness, .fs-sources li { align-items: flex-start; flex-direction: column; }
    .fs-break-row { grid-template-columns: minmax(76px, 105px) minmax(80px, 1fr) 44px; }
    .fs-zones { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .fs-gauge-value strong { font-size: 2.75rem; }
    .fs-gauge-value span { max-width: 72%; font-size: .82rem; }
    .fs-card strong { max-width: 100%; font-size: clamp(1.35rem, 8vw, 2rem); overflow-wrap: anywhere; }
    .fs-panel, .fs-card { padding: 18px; }
}
