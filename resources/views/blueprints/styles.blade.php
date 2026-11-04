*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--color-background);color:var(--color-text);font-family:var(--font-body);line-height:1.6;-webkit-font-smoothing:antialiased}
img,svg{max-width:100%;display:block}
a{color:inherit}
h1,h2,h3,h4{font-family:var(--font-display);font-weight:var(--heading-weight);line-height:1.12;margin:0;letter-spacing:var(--heading-tracking)}
h1{font-size:clamp(2.25rem,6vw,4.25rem)}
h2{font-size:clamp(1.75rem,3.6vw,2.75rem)}
h3{font-size:1.2rem}
p{margin:0}
:focus-visible{outline:3px solid var(--color-accent);outline-offset:3px}
.bp-sample-banner{background:var(--color-text);color:var(--color-background);font-size:.8rem;padding:.5rem 1.25rem;text-align:center}
.bp-wrap{max-width:var(--container);margin:0 auto;padding:0 1.25rem}
.bp-textlink{color:inherit;font:inherit;text-decoration:inherit}.bp-textlink:hover{text-decoration:underline;text-underline-offset:.2em}a.bp-textlink>.bp-visual,a.bp-textlink>.bp-mock{display:block}
.bp-section{padding:var(--space-section) 0}
.bp-section--alt{background:var(--color-surface)}
.bp-eyebrow{font-size:.78rem;font-weight:700;letter-spacing:var(--eyebrow-tracking);text-transform:var(--eyebrow-transform);color:var(--color-accent);margin-bottom:.9rem}
.bp-lead{font-size:clamp(1.05rem,1.6vw,1.25rem);color:var(--color-muted);max-width:42rem;margin-top:1.1rem}
.bp-head{max-width:46rem;margin-bottom:clamp(2rem,4vw,3.5rem)}
.bp-btn{display:inline-block;padding:.85rem 1.5rem;border-radius:var(--button-radius);font-weight:600;text-decoration:none;border:2px solid var(--color-primary);background:var(--color-primary);color:var(--color-primary-contrast);transition:transform .2s ease}
.bp-btn:hover{transform:translateY(-2px)}
.bp-btn--ghost{background:transparent;color:var(--color-primary)}
.bp-btn--light{background:var(--color-primary-contrast);color:var(--color-primary);border-color:var(--color-primary-contrast)}
.bp-btn--light-ghost{background:transparent;color:var(--color-primary-contrast);border-color:var(--color-primary-contrast)}
.bp-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.75rem}
.bp-card{background:var(--color-surface);border-radius:var(--radius);padding:1.5rem}
.bp-section--alt .bp-card{background:var(--color-background)}
.bp-grid{display:grid;gap:1.25rem;grid-template-columns:minmax(0,1fr)}
.bp-grid>*,.bp-bento>*,.bp-gal>*,.bp-steps>*,.bp-sched>*{min-width:0}
@media(max-width:767px){.bp-grid,.bp-bento,.bp-gal,.bp-steps,.bp-sched,.bp-gal--featured,.bp-gal--edge{grid-template-columns:minmax(0,1fr)!important}.bp-bento>*,.bp-gal>*{grid-column:auto!important}}
@media(min-width:768px){.bp-grid--2{grid-template-columns:repeat(2,1fr)}.bp-grid--3{grid-template-columns:repeat(3,1fr)}.bp-grid--4{grid-template-columns:repeat(4,1fr)}}
@media(min-width:768px) and (max-width:1023px){.bp-grid--4{grid-template-columns:repeat(2,1fr)}}
/* placeholder visual: brand-token gradient, used wherever an approved image is not (yet) bound */
.bp-visual{background:var(--color-surface);border-radius:var(--radius);min-height:14rem;position:relative;overflow:hidden}
.bp-art{position:absolute;inset:0;width:100%;height:100%;display:block}
.bp-visual--tall{min-height:22rem}
.bp-visual__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1}
/* A picture with see-through areas (logo, slogan, cut-out) is shown whole and on the page itself: no frame colour, no cropping, no clipping. */
.bp-visual--clear{background:transparent;overflow:visible;box-shadow:none;border-color:transparent}.bp-visual--clear .bp-visual__img{object-fit:contain}
.bp-icon{width:2.75rem;height:2.75rem;border-radius:var(--radius);background:var(--color-primary);color:var(--color-primary-contrast);display:grid;place-items:center;margin-bottom:1rem}
.bp-icon svg{width:1.4rem;height:1.4rem}
/* navigation */
.bp-nav{border-bottom:1px solid color-mix(in srgb,var(--color-text) 12%,transparent);background:var(--color-background);position:sticky;top:0;z-index:20}
.bp-nav__row{display:flex;align-items:center;justify-content:space-between;gap:1rem;min-height:4.25rem}
.bp-brand{font-family:var(--font-display);font-weight:800;font-size:1.2rem;text-decoration:none;display:inline-flex;align-items:center;gap:.6rem}
.bp-brand__logo{height:2.4rem;width:auto;max-width:13rem;object-fit:contain;display:block}
.bp-nav__links{display:none;gap:1.75rem;align-items:center}
.bp-nav__links a{text-decoration:none;font-size:.95rem;color:var(--color-muted)}
.bp-nav__links a:hover{color:var(--color-text)}
.bp-nav .bp-btn,.bp-nav .bp-btn:hover{color:var(--color-primary-contrast);font-size:.95rem;padding:.6rem 1.1rem}
.bp-nav__menu .bp-btn{text-align:center;color:var(--color-primary-contrast)}
.bp-nav details{position:relative}
.bp-nav summary{list-style:none;cursor:pointer;padding:.5rem .9rem;border:1px solid var(--color-muted);border-radius:var(--radius);font-weight:600}
.bp-nav summary::-webkit-details-marker{display:none}
.bp-nav__menu{position:absolute;right:0;top:calc(100% + .5rem);min-width:14rem;background:var(--color-background);border:1px solid color-mix(in srgb,var(--color-text) 15%,transparent);border-radius:var(--radius);padding:.75rem;display:grid;gap:.25rem}
.bp-nav__menu a{padding:.6rem .75rem;text-decoration:none}
.bp-nav--minimal{background:transparent;position:absolute;left:0;right:0;border:0;color:#fff}
.bp-nav--minimal .bp-nav__links a,.bp-nav--minimal .bp-brand{color:#fff}
.bp-nav--minimal summary{color:#fff;border-color:#fff}
.bp-nav--minimal .bp-nav__menu a{color:var(--color-text)}
@media(min-width:900px){.bp-nav__links{display:flex}.bp-nav details{display:none}}
/* hero */
.bp-hero{padding:clamp(3rem,7vw,6rem) 0}
.bp-hero__grid{display:grid;gap:2.5rem;align-items:center}
@media(min-width:900px){.bp-hero__grid{grid-template-columns:var(--cols,1.05fr .95fr)}}
.bp-mock{background:var(--color-surface);border-radius:calc(var(--radius) + 4px);border:1px solid color-mix(in srgb,var(--color-text) 14%,transparent);box-shadow:0 30px 60px -30px color-mix(in srgb,var(--color-text) 40%,transparent);overflow:hidden}
.bp-mock__bar{display:flex;gap:.4rem;padding:.7rem .9rem;border-bottom:1px solid color-mix(in srgb,var(--color-text) 10%,transparent)}
.bp-mock__bar i{width:.65rem;height:.65rem;border-radius:50%;background:var(--color-muted);opacity:.5}
.bp-mock__body{display:grid;grid-template-columns:5rem 1fr;gap:1rem;padding:1rem;min-height:15rem}
.bp-mock__side{display:grid;gap:.5rem;align-content:start}
.bp-mock__side i,.bp-mock__main i{display:block;height:.7rem;border-radius:99px;background:color-mix(in srgb,var(--color-text) 14%,transparent)}
.bp-mock__main{display:grid;gap:.7rem;align-content:start}
.bp-mock__main b{display:block;height:6rem;border-radius:var(--radius);background:linear-gradient(135deg,var(--color-primary),var(--color-accent))}
.bp-hero--aerial{min-height:min(48rem,92vh);display:flex;align-items:flex-end;color:#fff;background:var(--color-primary);position:relative;overflow:hidden;padding:8rem 0 clamp(3rem,7vw,5.5rem)}
.bp-hero__art{position:absolute;inset:0}
.bp-hero--aerial::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.2),rgba(0,0,0,.7));pointer-events:none}
.bp-hero--aerial .bp-wrap{position:relative;z-index:1}
.bp-hero--aerial .bp-lead,.bp-hero--aerial .bp-eyebrow{color:#fff}
.bp-hero--aerial h1{max-width:16ch;font-size:clamp(2.75rem,8vw,6rem)}
/* logos / stats */
.bp-logos{display:flex;flex-wrap:wrap;gap:1rem 2.5rem;align-items:center;justify-content:center}
.bp-logos span{font-family:var(--font-display);font-weight:700;font-size:1.15rem;color:var(--color-muted)}
.bp-center{text-align:center}
.bp-stat{font-family:var(--font-display);font-size:clamp(2.25rem,4.5vw,3.5rem);font-weight:800;color:var(--color-primary);line-height:1}
.bp-stat-label{color:var(--color-muted);margin-top:.5rem}
/* bento */
.bp-bento{display:grid;gap:1.25rem}
@media(min-width:768px){.bp-bento{grid-template-columns:repeat(6,1fr)}.bp-bento>:nth-child(1){grid-column:span 4}.bp-bento>:nth-child(2){grid-column:span 2}.bp-bento>:nth-child(3){grid-column:span 2}.bp-bento>:nth-child(4){grid-column:span 4}.bp-bento>:nth-child(n+5){grid-column:span 3}}
.bp-bento .bp-card h3{margin-bottom:.5rem}
.bp-card p{color:var(--color-muted)}
/* gallery */
.bp-gal{display:grid;gap:.75rem}
@media(min-width:768px){.bp-gal--featured{grid-template-columns:repeat(3,1fr)}.bp-gal--featured>:first-child{grid-column:1/-1}.bp-gal--featured>:first-child .bp-visual{min-height:24rem}.bp-gal--edge{grid-template-columns:repeat(3,1fr);gap:0}}
.bp-gal figure{margin:0;position:relative}
.bp-gal .bp-visual{min-height:15rem;height:100%}
.bp-gal figure{overflow:hidden}
.bp-gal--edge .bp-visual{border-radius:0;min-height:18rem}
.bp-gal figcaption{padding:.75rem .25rem;color:var(--color-muted);font-size:.9rem}
.bp-gal--edge figcaption{position:absolute;left:0;right:0;bottom:0;padding:1rem;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.6));z-index:1}
/* steps */
.bp-steps{display:grid;gap:1.5rem;counter-reset:s;padding:0;margin:0;list-style:none}
@media(min-width:768px){.bp-steps{grid-template-columns:repeat(3,1fr)}}
.bp-steps li{counter-increment:s;border-top:3px solid var(--color-primary);padding-top:1rem}
.bp-steps li::before{content:counter(s,decimal-leading-zero);font-family:var(--font-display);font-weight:800;color:var(--color-accent);font-size:1.1rem}
.bp-steps h3{margin:.4rem 0 .5rem}
.bp-steps p{color:var(--color-muted)}
/* pricing */
.bp-price{font-family:var(--font-display);font-size:2.5rem;font-weight:800;margin:.75rem 0}
.bp-price small{font-size:1rem;font-weight:400;color:var(--color-muted)}
.bp-card--hl{border:2px solid var(--color-primary)}
.bp-ticks{list-style:none;padding:0;margin:1rem 0 1.5rem;display:grid;gap:.5rem}
.bp-ticks li::before{content:"✓";color:var(--color-primary);font-weight:800;margin-right:.6rem}
/* faq */
.bp-faq{max-width:48rem;margin:0 auto}
.bp-faq details{border-bottom:1px solid color-mix(in srgb,var(--color-text) 15%,transparent);padding:1.1rem 0}
.bp-faq summary{cursor:pointer;font-weight:600;font-size:1.1rem;list-style:none;display:flex;justify-content:space-between;gap:1rem}
.bp-faq summary::after{content:"+";font-size:1.4rem;line-height:1}
.bp-faq details[open] summary::after{content:"–"}
.bp-faq p{color:var(--color-muted);margin-top:.75rem}
/* cta */
.bp-cta{background:var(--color-primary);color:var(--color-primary-contrast)}
.bp-cta .bp-lead{color:inherit;opacity:.9}
.bp-cta--bold{text-align:center;padding:clamp(5rem,12vw,9rem) 0}
.bp-cta--bold h2{font-size:clamp(2.5rem,7vw,5rem);max-width:18ch;margin:0 auto}
.bp-cta--bold .bp-lead{margin-left:auto;margin-right:auto}
.bp-cta--bold .bp-actions{justify-content:center}
.bp-cta__split{display:grid;gap:1.5rem;align-items:center}
@media(min-width:768px){.bp-cta__split{grid-template-columns:var(--cols,1.4fr 1fr)}.bp-cta__split .bp-actions{justify-content:flex-end;margin-top:0}}
/* testimonials / quotes */
.bp-quote{font-size:1.05rem}
.bp-quote footer{margin-top:1rem;font-size:.9rem;color:var(--color-muted)}
.bp-quote strong{color:var(--color-text);display:block}
.bp-bigquote{max-width:52rem;margin:0 auto 3rem;text-align:center}
.bp-bigquote blockquote{margin:0;font-family:var(--font-display);font-size:clamp(1.4rem,3vw,2.1rem);line-height:1.3}
.bp-bigquote cite{display:block;margin-top:1rem;font-style:normal;color:var(--color-muted)}
/* contact form */
.bp-contact{display:grid;gap:2.5rem}
@media(min-width:900px){.bp-contact{grid-template-columns:var(--cols,1.2fr .8fr);align-items:start}}
.bp-details--card{background:var(--color-background);border:1px solid color-mix(in srgb,var(--color-text) 12%,transparent);border-radius:var(--radius);padding:1.25rem 1.5rem;margin:0}
.bp-form{display:grid;gap:1rem}
.bp-form label{display:grid;gap:.4rem;font-weight:600;font-size:.92rem}
.bp-form input,.bp-form textarea{font:inherit;padding:.8rem 1rem;border:1px solid color-mix(in srgb,var(--color-text) 30%,transparent);border-radius:var(--radius);background:var(--color-background);color:var(--color-text)}
.bp-form textarea{min-height:8rem;resize:vertical}
.bp-details dt{font-size:.78rem;text-transform:uppercase;letter-spacing:.1em;color:var(--color-muted);margin-top:1.1rem}
.bp-details dd{margin:.15rem 0 0;font-weight:600}
/* schedule */
.bp-sched{display:grid;gap:.75rem}
@media(min-width:768px){.bp-sched{grid-template-columns:repeat(3,1fr)}}
@media(min-width:1100px){.bp-sched{grid-template-columns:repeat(auto-fit,minmax(9.5rem,1fr))}}
.bp-sched h3{font-size:1rem;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.75rem;color:var(--color-primary)}
.bp-sched ul{list-style:none;margin:0;padding:0;display:grid;gap:.6rem}
.bp-sched time{display:block;font-size:.8rem;color:var(--color-muted)}
/* split */
.bp-split{display:grid;gap:2.5rem;align-items:center}
@media(min-width:900px){.bp-split{grid-template-columns:var(--cols,1fr 1fr)}}
/* footer */
.bp-footer{background:var(--color-text);color:var(--color-background);padding:clamp(3rem,6vw,5rem) 0 2rem}
.bp-footer a{color:inherit;opacity:.8;text-decoration:none}
.bp-footer a:hover{opacity:1;text-decoration:underline}
.bp-footer__grid{display:grid;gap:2rem}
@media(min-width:768px){.bp-footer__grid{grid-template-columns:1.4fr repeat(3,1fr)}}
.bp-footer h4{font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;margin-bottom:.9rem;opacity:.7}
.bp-footer ul{list-style:none;margin:0;padding:0;display:grid;gap:.5rem}
.bp-footer__legal{margin-top:2.5rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,.18);font-size:.85rem;opacity:.7}
/* ---- v1.5 layout variants ---- */
.bp-pill{display:inline-block;font-size:.8rem;font-weight:600;padding:.35rem .9rem;border-radius:999px;background:var(--color-surface);color:var(--color-primary);border:1px solid color-mix(in srgb,var(--color-primary) 25%,transparent);margin-bottom:1.25rem}
.bp-visual--portrait{aspect-ratio:4/5;min-height:0;width:100%}
.bp-visual--square{aspect-ratio:1/1;min-height:0;width:100%}
.bp-visual--chip{width:3.5rem;height:3.5rem;min-height:0;border-radius:calc(var(--radius) + 2px)}
.bp-visual--strip{min-height:0;height:clamp(9rem,18vw,15rem);border-radius:0;margin-top:clamp(2.5rem,6vw,4.5rem)}
.bp-nav--floating{background:transparent;border:0;top:.75rem;padding-top:.75rem}
.bp-nav__pill{display:flex;align-items:center;justify-content:space-between;gap:1rem;background:var(--color-background);border:1px solid color-mix(in srgb,var(--color-text) 14%,transparent);border-radius:999px;padding:.55rem .6rem .55rem 1.4rem;box-shadow:0 14px 34px -18px color-mix(in srgb,var(--color-text) 45%,transparent)}
.bp-nav__pill .bp-nav__links{margin:0 auto}
.bp-nav__cta{display:none}
@media(min-width:900px){.bp-nav__cta{display:block}}
.bp-ed{padding:clamp(3rem,6vw,5.5rem) 0 var(--space-section)}
.bp-ed__grid{display:grid;gap:2.5rem}
.bp-ed__title{font-size:clamp(2.5rem,6.6vw,6rem);line-height:.98;letter-spacing:var(--heading-tracking);overflow-wrap:break-word;hyphens:auto}
.bp-ed__lead{font-size:1.1rem;color:var(--color-muted);margin-top:1.5rem}
@media(min-width:900px){.bp-ed__grid{grid-template-columns:var(--cols,repeat(12,1fr));align-items:start}.bp-ed__text{grid-column:var(--ed-text,1/9);padding-top:2rem}.bp-ed__aside{grid-column:var(--ed-aside,9/13);margin-top:7rem}}
.bp-typo{padding:clamp(3rem,7vw,6rem) 0 0;overflow:hidden}
.bp-typo__eyebrow{display:flex;align-items:center;gap:1rem}
.bp-typo__eyebrow::after{content:"";flex:1;height:1px;background:color-mix(in srgb,var(--color-text) 25%,transparent)}
.bp-typo__title{font-size:clamp(2.7rem,8.6vw,7.4rem);line-height:.94;letter-spacing:var(--heading-tracking);max-width:15ch;overflow-wrap:break-word}
.bp-typo__row{display:grid;gap:1.5rem;margin-top:clamp(1.5rem,4vw,3rem);align-items:end}
.bp-typo__lead{font-size:clamp(1.05rem,1.7vw,1.3rem);color:var(--color-muted);max-width:34rem}
@media(min-width:900px){.bp-typo__row{grid-template-columns:var(--cols,1.3fr 1fr)}.bp-typo__row .bp-actions{justify-content:flex-end;margin-top:0}}
.bp-centered{position:relative;overflow:hidden;padding:clamp(3.5rem,7vw,6rem) 0 clamp(2rem,4vw,3rem)}
.bp-centered__glow{position:absolute;inset:0;opacity:.2;pointer-events:none;-webkit-mask-image:linear-gradient(#000 0%,transparent 78%);mask-image:linear-gradient(#000 0%,transparent 78%)}
.bp-centered>.bp-wrap{position:relative}
.bp-centered__title{font-size:clamp(2.6rem,7.5vw,5.6rem);line-height:1;max-width:16ch;margin:0 auto}
.bp-centered__mock{max-width:60rem;margin:clamp(2.5rem,5vw,4rem) auto 0;text-align:left}
.bp-mock__art{position:relative;height:clamp(14rem,30vw,26rem)}
.bp-rows{list-style:none;margin:0;padding:0;border-top:1px solid color-mix(in srgb,var(--color-text) 22%,transparent)}
.bp-row{display:grid;gap:.5rem 1.5rem;padding:1.6rem 0;border-bottom:1px solid color-mix(in srgb,var(--color-text) 22%,transparent);align-items:baseline}
.bp-row__n{font-family:var(--font-display);color:var(--color-accent);font-size:1rem}
.bp-row__t{font-size:clamp(1.5rem,3.2vw,2.5rem)}
.bp-row__d{color:var(--color-muted)}
.bp-row__m{display:block;margin-top:.5rem;font-size:.78rem;letter-spacing:var(--eyebrow-tracking);text-transform:var(--eyebrow-transform);color:var(--color-accent);font-weight:700}
@media(min-width:900px){.bp-row{grid-template-columns:4rem 1.1fr 1fr}}
.bp-tile{display:block}
.bp-ifeat article h3{margin:1rem 0 .4rem}
.bp-ifeat article p{color:var(--color-muted)}
.bp-icon--n{font-family:var(--font-display);font-weight:700}
.bp-cta-min{border-top:1px solid color-mix(in srgb,var(--color-text) 20%,transparent);border-bottom:1px solid color-mix(in srgb,var(--color-text) 20%,transparent)}
.bp-cta-min__row{display:grid;gap:1.5rem;align-items:center}
.bp-cta-min h2{font-size:clamp(1.6rem,3.4vw,2.6rem)}
@media(min-width:768px){.bp-cta-min__row{grid-template-columns:var(--cols,1.5fr 1fr)}.bp-cta-min__row .bp-actions{justify-content:flex-end}}
.bp-footer--min{padding:2rem 0}
.bp-footer__row{display:flex;flex-wrap:wrap;gap:1rem 2rem;align-items:center;justify-content:space-between}
.bp-footer__row ul{display:flex;flex-wrap:wrap;gap:.5rem 1.5rem}
.bp-product h3{font-size:1.05rem;margin-top:1rem}
.bp-product__d{color:var(--color-muted);font-size:.92rem;margin-top:.25rem}
.bp-product__p{font-family:var(--font-display);font-weight:700;margin:.6rem 0 .9rem}
.bp-product .bp-btn{padding:.55rem 1rem;font-size:.9rem}
.bp-product__media{position:relative}
.bp-badge{position:absolute;top:.75rem;left:.75rem;z-index:2;background:var(--color-text);color:var(--color-background);font-size:.72rem;font-weight:700;padding:.25rem .6rem;border-radius:999px;letter-spacing:.04em;text-transform:uppercase}
@media(prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important}.bp-btn:hover{transform:none}}
/* ---- Add-on widgets ---- */
.bp-widget{position:relative}
.bp-widget--hero .bp-widget-h1{font-family:var(--font-display);font-weight:var(--heading-weight);line-height:1.08;letter-spacing:var(--heading-tracking);font-size:clamp(2.25rem,6vw,4.25rem)}
.bpw-ph{display:flex;flex-direction:column;gap:.3rem;align-items:center;justify-content:center;text-align:center;min-height:9rem;padding:1.5rem;border:2px dashed color-mix(in srgb,var(--color-text) 22%,transparent);border-radius:var(--radius);color:var(--color-muted);font-size:.95rem}
.bpw-ph strong{color:var(--color-text);font-size:1rem}
.bpw-h{margin-bottom:1.25rem}
.bpw-embed,.bpw-map{display:block;width:100%;border:0;border-radius:var(--radius);background:var(--color-surface)}
.bpw-ratio{position:relative;aspect-ratio:16/9;border-radius:var(--radius);overflow:hidden;background:#000}.bpw-ratio iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.bpw-ph--video{aspect-ratio:16/9;background:#0f0f0f;color:#bbb;border-color:#444}.bpw-ph--video strong{color:#fff;font-size:1.4rem}
/* form */
.bpw-form{max-width:44rem;margin:0 auto}.bpw-form--card{background:var(--color-surface);border:1px solid color-mix(in srgb,var(--color-text) 10%,transparent);border-radius:calc(var(--radius) + 4px);padding:clamp(1.25rem,4vw,2.25rem);box-shadow:0 18px 50px -28px color-mix(in srgb,var(--color-text) 40%,transparent)}
.bpw-form .bp-form{margin-top:1.25rem}.bpw-note{font-size:.82rem;color:var(--color-muted);margin-top:.75rem}
/* social */
.bpw-social{text-align:center}.bpw-social.bpw-align--left{text-align:left}.bpw-social__h{margin-bottom:1rem;font-size:1.05rem;color:var(--color-muted);font-weight:600}
.bpw-social__list{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center}.bpw-align--left .bpw-social__list{justify-content:flex-start}
.bpw-social a{display:inline-flex;align-items:center;gap:.55rem;text-decoration:none;color:#fff;transition:transform .18s ease,box-shadow .18s ease}
.bpw-ic{display:inline-flex;font-size:1.35rem}.bpw-social--round a{width:3rem;height:3rem;justify-content:center;border-radius:999px;background:var(--bp-ic-bg)}
.bpw-social--round a:hover,.bpw-social--buttons a:hover{transform:translateY(-3px);box-shadow:0 10px 24px -10px var(--bp-ic-bg)}
.bpw-social--buttons a{padding:.7rem 1.15rem;border-radius:var(--button-radius);background:var(--bp-ic-bg);font-weight:600;font-size:.95rem}
.bpw-social--minimal a{color:var(--color-text);width:2.5rem;height:2.5rem;justify-content:center;border-radius:999px;background:color-mix(in srgb,var(--color-text) 8%,transparent)}.bpw-social--minimal a:hover{background:var(--bp-ic-bg);color:#fff}
.bpw-social--minimal .bpw-ic,.bpw-social--round .bpw-ic{font-size:1.3rem}
/* testimonials carousel */
.bpw-tcar__h{text-align:center;margin-bottom:2rem}.bpw-tcar__viewport{overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;outline-offset:6px;border-radius:var(--radius)}.bpw-tcar__viewport::-webkit-scrollbar{display:none}
.bpw-tcar__track{display:flex;list-style:none;margin:0;padding:0}.bpw-tcar__slide{flex:0 0 100%;scroll-snap-align:center;padding:0 .25rem;box-sizing:border-box}
.bpw-quote{margin:0 auto;max-width:46rem;text-align:center;padding:clamp(1.5rem,4vw,2.5rem);background:var(--color-surface);border-radius:calc(var(--radius) + 4px);border:1px solid color-mix(in srgb,var(--color-text) 10%,transparent)}
.bpw-quote blockquote{margin:0;font-size:clamp(1.1rem,2.2vw,1.5rem);line-height:1.5;font-family:var(--font-display)}.bpw-quote figcaption{margin-top:1.25rem;display:flex;flex-direction:column;gap:.15rem}.bpw-quote figcaption span{color:var(--color-muted);font-size:.9rem}
.bpw-tcar__nav{display:flex;align-items:center;justify-content:center;gap:1rem;margin-top:1.25rem}.bpw-tcar__nav[hidden]{display:none}
.bpw-tcar__nav button{width:2.5rem;height:2.5rem;border-radius:999px;border:1px solid color-mix(in srgb,var(--color-text) 20%,transparent);background:transparent;color:var(--color-text);font-size:1.4rem;line-height:1;cursor:pointer}.bpw-tcar__nav button:hover{background:var(--color-primary);color:var(--color-primary-contrast)}
.bpw-tcar__dots{display:flex;gap:.45rem}.bpw-tcar__dots i{width:.55rem;height:.55rem;border-radius:999px;background:color-mix(in srgb,var(--color-text) 22%,transparent);transition:background .2s,transform .2s}.bpw-tcar__dots i.on{background:var(--color-accent);transform:scale(1.25)}
/* CTA banner + announcement */
.bpw-cta{display:flex;flex-wrap:wrap;gap:1.25rem;align-items:center;justify-content:space-between;background:var(--color-primary);color:var(--color-primary-contrast);border-radius:calc(var(--radius) + 6px);padding:clamp(1.75rem,5vw,3rem)}.bpw-cta h2{color:inherit}.bpw-cta p{margin-top:.5rem;opacity:.85;max-width:38rem}
.bpw-cta .bp-btn{background:var(--color-primary-contrast);color:var(--color-primary);border-color:var(--color-primary-contrast)}
.bpw-announce{background:var(--color-accent);color:#fff;font-size:.9rem;text-align:center;padding:.6rem 0}.bpw-announce .bp-wrap{display:flex;gap:.5rem 1rem;flex-wrap:wrap;justify-content:center}.bpw-announce a{color:#fff;font-weight:700;text-underline-offset:3px}
/* floating chat */
.bpw-fab{position:fixed;bottom:1.1rem;z-index:60;display:flex;flex-direction:column;align-items:flex-end;gap:.6rem}.bpw-fab--right{right:1.1rem}.bpw-fab--left{left:1.1rem;align-items:flex-start}
.bpw-fab__btn{width:3.4rem;height:3.4rem;border-radius:999px;background:var(--bp-ic-bg,var(--color-primary));color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.7rem;box-shadow:0 10px 28px -8px rgba(0,0,0,.45);cursor:pointer;text-decoration:none;transition:transform .18s}.bpw-fab__btn:hover{transform:scale(1.07)}
.bpw-fab__main{background:var(--color-primary);color:var(--color-primary-contrast)}
.bpw-fab__toggle{position:absolute;opacity:0;pointer-events:none}.bpw-fab__toggle:focus-visible+.bpw-fab__list+.bpw-fab__main{outline:3px solid var(--color-accent);outline-offset:3px}
.bpw-fab__list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.55rem;align-items:flex-end;opacity:0;visibility:hidden;transform:translateY(10px);transition:opacity .18s,transform .18s,visibility .18s}.bpw-fab--left .bpw-fab__list{align-items:flex-start}
.bpw-fab__toggle:checked+.bpw-fab__list{opacity:1;visibility:visible;transform:none}
.bpw-fab__list a{display:flex;align-items:center;gap:.6rem;text-decoration:none;flex-direction:row-reverse}.bpw-fab--left .bpw-fab__list a{flex-direction:row}
.bpw-fab__ic{width:2.9rem;height:2.9rem;border-radius:999px;background:var(--bp-ic-bg);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:1.45rem;box-shadow:0 8px 20px -8px rgba(0,0,0,.45)}
.bpw-fab__tip{background:#fff;color:#111;border-radius:999px;padding:.35rem .75rem;font-size:.82rem;font-weight:600;box-shadow:0 6px 16px -6px rgba(0,0,0,.35);white-space:nowrap}
@media(prefers-reduced-motion:reduce){.bpw-fab__list,.bpw-fab__btn,.bpw-social a{transition:none}}
@media(max-width:600px){.bpw-fab{bottom:.8rem}.bpw-fab--right{right:.8rem}.bpw-fab--left{left:.8rem}}
/* ---- Hero layouts: image-left, banner (content over a full-width image), full-screen (image on one side), bold type ---- */
.bp-hero__art svg,.bp-full__art svg{width:100%;height:100%;display:block}
@media(min-width:900px){.bp-hero__grid--rev{grid-template-columns:var(--cols,.95fr 1.05fr)}.bp-hero__grid--rev>.bp-visual{order:-1}}
.bp-btn--icon{display:inline-flex;align-items:center;gap:.6rem}.bp-btn--icon svg{flex:none;transition:transform .2s}.bp-btn--icon:hover svg{transform:translate(2px,-2px)}
/* banner */
.bp-hero--banner{position:relative;overflow:hidden;color:#fff;background:var(--color-primary);padding:0;min-height:clamp(30rem,72vh,44rem);display:flex;align-items:center}
.bp-hero--banner::after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(90deg,rgba(0,0,0,.74) 0%,rgba(0,0,0,.5) 45%,rgba(0,0,0,.08) 100%)}
.bp-hero--banner-right::after{background:linear-gradient(270deg,rgba(0,0,0,.74) 0%,rgba(0,0,0,.5) 45%,rgba(0,0,0,.08) 100%)}
.bp-banner__wrap{position:relative;z-index:1;width:100%;padding-top:clamp(5rem,9vw,7rem);padding-bottom:clamp(3rem,7vw,5.5rem)}
.bp-banner__content{max-width:38rem}.bp-hero--banner-right .bp-banner__content{margin-left:auto;text-align:left}
.bp-hero--banner .bp-eyebrow,.bp-hero--banner .bp-lead{color:#fff}.bp-hero--banner .bp-lead{opacity:.92}
.bp-hero--banner h1{color:#fff;font-size:clamp(2.4rem,5.6vw,4.4rem)}
@media(max-width:767px){.bp-hero--banner::after,.bp-hero--banner-right::after{background:linear-gradient(180deg,rgba(0,0,0,.62),rgba(0,0,0,.8))}}
/* full-screen with the picture on one side — the text side stays clear */
.bp-hero--full{position:relative;overflow:hidden;padding:0;min-height:calc(100svh - 4.5rem);display:flex;flex-direction:column;background:var(--color-background)}
.bp-full__art{position:relative;height:38svh;min-height:14rem}
.bp-full__art .bp-visual__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.bp-full__wrap{position:relative;z-index:1;flex:1;display:flex;align-items:center;padding-top:clamp(2rem,6vw,3rem);padding-bottom:clamp(2.5rem,6vw,4rem)}
.bp-full__content h1{font-size:clamp(2.3rem,5vw,4.6rem);line-height:1.04}
.bp-full__content .bp-lead{max-width:34rem}
@media(min-width:900px){
.bp-hero--full{display:flex;flex-direction:row;align-items:center}
.bp-full__art{position:absolute;top:0;bottom:0;height:auto;width:62%}
.bp-hero--full-left .bp-full__art{left:0;-webkit-mask-image:linear-gradient(90deg,#000 55%,transparent);mask-image:linear-gradient(90deg,#000 55%,transparent)}
.bp-hero--full-right .bp-full__art{right:0;-webkit-mask-image:linear-gradient(270deg,#000 55%,transparent);mask-image:linear-gradient(270deg,#000 55%,transparent)}
.bp-full__wrap{width:100%;padding-top:5rem;padding-bottom:5rem}
.bp-full__content{max-width:min(36rem,48%)}
.bp-hero--full-left .bp-full__content{margin-left:auto}
}
/* bold type: huge title, description and icon buttons along the bottom */
.bp-hero--bold{padding:clamp(3rem,8vw,6.5rem) 0 clamp(2.5rem,6vw,4.5rem)}
.bp-bold__title{font-size:clamp(3rem,11vw,9rem);line-height:.95;letter-spacing:-.03em;overflow-wrap:break-word;hyphens:auto;max-width:14ch}
.bp-bold__top{min-height:clamp(10rem,32vw,24rem);display:flex;flex-direction:column;justify-content:flex-end}
.bp-bold__bottom{display:flex;flex-direction:column;gap:1.5rem;margin-top:clamp(2rem,5vw,3.5rem);padding-top:clamp(1.5rem,3vw,2.25rem);border-top:2px solid color-mix(in srgb,var(--color-text) 85%,transparent)}
.bp-bold__lead{margin-top:0;max-width:36rem}.bp-bold__actions{margin-top:0}
@media(min-width:900px){
.bp-bold__bottom{flex-direction:row;align-items:flex-end;justify-content:space-between}
.bp-hero--bold-right .bp-bold__bottom{flex-direction:row}
.bp-hero--bold-left .bp-bold__bottom{flex-direction:row-reverse}
.bp-hero--bold-left .bp-bold__top{align-items:flex-start}.bp-hero--bold-right .bp-bold__title{margin-left:auto;text-align:right}.bp-hero--bold-right .bp-bold__top{align-items:flex-end}
}
.bp-hero--bold .bp-visual--strip{margin-top:clamp(1.5rem,4vw,2.5rem);border-radius:var(--radius)}
