.government-tenders-shell {
    --gt-ink: #102933;
    --gt-muted: #6d7f87;
    --gt-paper: #f4f7f3;
    --gt-card: #ffffff;
    --gt-green: #087c55;
    --gt-green-soft: #dff3e9;
    --gt-red: #d92f32;
    --gt-gold: #d6a229;
    --gt-line: #dbe4e2;
    margin: 0;
    color: var(--gt-ink);
    background:
        radial-gradient(circle at 8% 8%, rgba(8, 124, 85, .09), transparent 24rem),
        linear-gradient(180deg, #f8faf7 0, var(--gt-paper) 44rem);
    font-family: "Noto Kufi Arabic", "Noto Sans Arabic", Tahoma, sans-serif;
}
.gt-wrap { width: min(1280px, calc(100% - 40px)); margin-inline: auto; }
.gt-hero {
    position: relative;
    overflow: hidden;
    padding: 54px 0 88px;
    color: white;
    background:
        radial-gradient(circle at 16% 18%, rgba(65, 190, 139, .24), transparent 26rem),
        linear-gradient(115deg, rgba(5, 37, 49, .99), rgba(8, 105, 76, .94)),
        repeating-linear-gradient(45deg, transparent 0 18px, rgba(255,255,255,.04) 18px 19px);
}
.gt-hero::after {
    position: absolute;
    inset: auto -8% -105px auto;
    width: 420px;
    height: 240px;
    border: 42px solid rgba(255,255,255,.06);
    border-radius: 50%;
    content: "";
    transform: rotate(-12deg);
}
.gt-kicker { margin: 0 0 12px; color: #85e0b9; font-size: .92rem; font-weight: 800; letter-spacing: .05em; }
.gt-hero h1 { max-width: 760px; margin: 0; font-size: clamp(2.2rem, 5vw, 4rem); line-height: 1.12; letter-spacing: -.035em; }
.gt-hero p { max-width: 720px; margin: 18px 0 0; color: #dcebea; font-size: 1.05rem; line-height: 1.9; }
.gt-counters {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: -48px;
}
.gt-counter {
    position: relative;
    overflow: hidden;
    padding: 25px;
    border: 1px solid rgba(16,41,51,.08);
    border-radius: 22px;
    background: rgba(255,255,255,.96);
    box-shadow: 0 18px 48px rgba(16,41,51,.1);
}
.gt-counter::after { position: absolute; inset: auto auto -34px -24px; width: 105px; height: 105px; border: 18px solid rgba(8,124,85,.07); border-radius: 50%; content: ""; }
.gt-counter span { display: block; color: var(--gt-muted); font-weight: 700; }
.gt-counter strong { display: block; margin-top: 7px; color: var(--gt-green); font-size: 2.35rem; line-height: 1; }
.gt-main { padding: 30px 0 72px; }
.gt-freshness {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
    padding: 13px 17px;
    border: 1px solid #bcd9cd;
    border-radius: 14px;
    color: #215947;
    background: #eef8f3;
    font-size: .9rem;
}
.gt-freshness.is-warning { border-color: #edd8a3; color: #73550a; background: #fff8e6; }
.gt-filter-panel {
    padding: 26px;
    border: 1px solid var(--gt-line);
    border-radius: 24px;
    background: var(--gt-card);
    box-shadow: 0 18px 48px rgba(16,41,51,.07);
}
.gt-search-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; }
.gt-search-row input,
.gt-filter-grid select,
.gt-filter-grid input {
    min-width: 0;
    height: 48px;
    padding: 0 15px;
    border: 1px solid #cbd8d5;
    border-radius: 12px;
    color: var(--gt-ink);
    background: white;
    font: inherit;
}
.gt-search-row input:focus,
.gt-filter-grid select:focus,
.gt-filter-grid input:focus { outline: 3px solid rgba(8,124,85,.16); border-color: var(--gt-green); }
.gt-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 0 21px;
    border: 0;
    border-radius: 12px;
    color: white;
    background: var(--gt-green);
    font: inherit;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
}
.gt-button:hover { background: #056443; }
.gt-button.is-outline { border: 1px solid currentColor; color: var(--gt-green); background: transparent; }
.gt-button.is-official { background: var(--gt-red); }
.gt-filter-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-top: 12px; }
.gt-section-heading { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin: 34px 0 17px; }
.gt-section-heading h2 { margin: 0; font-size: clamp(1.5rem, 3vw, 2.2rem); }
.gt-section-heading p { margin: 0; color: var(--gt-muted); }
.gt-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.gt-card {
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 290px;
    padding: 24px;
    border: 1px solid var(--gt-line);
    border-radius: 22px;
    background: var(--gt-card);
    box-shadow: 0 12px 32px rgba(16,41,51,.055);
    transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}
.gt-card::before { position: absolute; top: 0; right: 0; bottom: 0; width: 4px; background: var(--gt-green); content: ""; }
.gt-card:hover { transform: translateY(-4px); border-color: rgba(8,124,85,.35); box-shadow: 0 22px 50px rgba(16,41,51,.11); }
.gt-card-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.gt-status { display: inline-flex; padding: 7px 12px; border-radius: 999px; color: #126344; background: var(--gt-green-soft); font-size: .84rem; font-weight: 900; }
.gt-status.closing_soon { color: #7b5600; background: #fff0c5; }
.gt-status.expired { color: #58686e; background: #edf1f1; }
.gt-status.cancelled { color: #9b2022; background: #fde7e7; }
.gt-status.awarded { color: #145890; background: #e5f1fb; }
.gt-reference { color: var(--gt-muted); direction: ltr; font-size: .82rem; }
.gt-card h3 { margin: 18px 0 10px; font-size: 1.16rem; line-height: 1.65; }
.gt-card h3 a { color: inherit; text-decoration: none; }
.gt-card h3 a:hover { color: var(--gt-green); }
.gt-entity { margin: 0 0 18px; color: var(--gt-muted); line-height: 1.7; }
.gt-card-details { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; margin-top: auto; padding-top: 16px; border-top: 1px solid var(--gt-line); }
.gt-card-details small, .gt-field small { display: block; margin-bottom: 5px; color: var(--gt-muted); }
.gt-deadline strong { color: var(--gt-red); }
.gt-card-link { margin-top: 20px; color: var(--gt-green); font-weight: 900; text-decoration: none; }
.gt-empty { padding: 64px 24px; border: 1px dashed #b8c9c5; border-radius: 22px; text-align: center; background: rgba(255,255,255,.65); }
.gt-empty h3 { margin: 0 0 8px; }
.gt-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; margin-top: 34px; }
.gt-pagination a, .gt-pagination span { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 16px; border: 1px solid var(--gt-line); border-radius: 13px; background: white; }
.gt-pagination a { color: var(--gt-green); font-weight: 900; text-decoration: none; box-shadow: 0 6px 18px rgba(16,41,51,.05); }
.gt-pagination a:hover { color: white; border-color: var(--gt-green); background: var(--gt-green); }
.gt-pagination span { color: var(--gt-muted); }
.gt-pagination a:first-child::before { margin-left: 7px; content: "→"; }
.gt-pagination a:last-child::after { margin-right: 7px; content: "←"; }
.gt-detail-hero { padding-bottom: 55px; }
.gt-back { display: inline-block; margin-bottom: 24px; color: #aee8cd; font-weight: 800; text-decoration: none; }
.gt-detail-title { max-width: 930px !important; font-size: clamp(1.75rem, 4vw, 3.25rem) !important; }
.gt-detail-summary {
    position: relative;
    z-index: 2;
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 18px;
    margin-top: -30px;
}
.gt-panel { padding: 28px; border: 1px solid var(--gt-line); border-radius: 24px; background: white; box-shadow: 0 14px 40px rgba(16,41,51,.075); }
.gt-panel h2 { margin: 0 0 20px; font-size: 1.35rem; }
.gt-deadline-panel { color: white; background: var(--gt-red); border-color: var(--gt-red); }
.gt-deadline-panel small { color: #ffdada; }
.gt-deadline-panel strong { display: block; margin-top: 9px; font-size: clamp(1.45rem, 4vw, 2.25rem); direction: ltr; text-align: right; }
.gt-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1px; overflow: hidden; border: 1px solid var(--gt-line); border-radius: 18px; background: var(--gt-line); }
.gt-field { min-height: 104px; padding: 19px; background: white; line-height: 1.75; overflow-wrap: anywhere; }
.gt-field.is-wide { grid-column: 1 / -1; }
.gt-content-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; margin-top: 22px; align-items: start; }
.gt-stack { display: grid; gap: 18px; }
.gt-participation { border-top: 5px solid var(--gt-green); }
.gt-participation-list { display: grid; gap: 14px; margin: 0; }
.gt-participation-row { padding-bottom: 14px; border-bottom: 1px solid var(--gt-line); }
.gt-participation-row:last-child { padding-bottom: 0; border-bottom: 0; }
.gt-participation-row dt { color: var(--gt-muted); font-size: .86rem; font-weight: 800; }
.gt-participation-row dd { margin: 5px 0 0; line-height: 1.8; white-space: pre-line; }
.gt-documents { display: grid; gap: 9px; margin: 0; padding: 0; list-style: none; }
.gt-documents a { display: flex; justify-content: space-between; gap: 10px; padding: 12px 14px; border-radius: 12px; color: var(--gt-ink); background: #f3f7f5; text-decoration: none; overflow-wrap: anywhere; }
.gt-documents a:hover { color: var(--gt-green); }
.gt-stage-line { display: grid; gap: 12px; }
.gt-stage { position: relative; padding: 0 25px 17px 0; border-right: 2px solid #c9d8d4; }
.gt-stage::before { position: absolute; top: 3px; right: -7px; width: 12px; height: 12px; border-radius: 50%; background: var(--gt-green); content: ""; }
.gt-stage strong, .gt-stage a { color: var(--gt-ink); }
.gt-stage a { display: block; margin-top: 4px; color: var(--gt-green); text-decoration: none; }
.gt-source-note { color: var(--gt-muted); font-size: .86rem; line-height: 1.7; }
.site-nav-tender-link { text-decoration: none; }
@media (max-width: 920px) {
    .gt-filter-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .gt-content-grid { grid-template-columns: 1fr; }
    .gt-detail-summary { grid-template-columns: 1fr; }
}
@media (max-width: 680px) {
    .gt-wrap { width: min(100% - 20px, 1180px); }
    .gt-hero { padding: 42px 0 76px; }
    .gt-counters { grid-template-columns: 1fr; margin-top: -42px; }
    .gt-counter { display: flex; align-items: center; justify-content: space-between; padding: 17px 19px; }
    .gt-counter strong { margin: 0; }
    .gt-search-row { grid-template-columns: 1fr; }
    .gt-filter-grid { grid-template-columns: 1fr 1fr; }
    .gt-grid, .gt-fields { grid-template-columns: 1fr; }
    .gt-field.is-wide { grid-column: auto; }
    .gt-section-heading, .gt-freshness { align-items: flex-start; flex-direction: column; }
    .gt-card { min-height: 0; padding: 19px; }
    .gt-card-details { grid-template-columns: 1fr; }
}
