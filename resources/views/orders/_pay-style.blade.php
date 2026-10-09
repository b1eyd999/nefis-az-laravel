{{-- The payment page's own look, shared by the order's payment page and
     by the page for money owed over a change made after it was paid. --}}
  .pay-grid{ display:grid; gap:1.5rem; grid-template-columns:minmax(0,1fr); max-width:44rem; margin-inline:auto; }
  .pay-card{ border:1px solid var(--line); border-radius:1rem; background:var(--paper); padding:1.25rem 1.35rem; }
  .pay-total{ display:flex; justify-content:space-between; align-items:baseline; gap:1rem; }
  .pay-total b{ font-size:1.6rem; color:var(--gold-deep); font-variant-numeric:tabular-nums; }
  .pay-methods{ display:grid; grid-template-columns:repeat(auto-fit, minmax(10rem, 1fr)); gap:.75rem; margin:.75rem 0 0; }
  .pay-method{
    position:relative; display:flex; flex-direction:column; align-items:center; gap:.4rem; padding:1rem .75rem;
    border:1.5px solid var(--line); border-radius:.9rem; background:var(--paper); color:var(--cocoa); text-align:center;
    transition:border-color .2s, box-shadow .2s, background .2s;
  }
  .pay-method:hover{ border-color:var(--gold); }
  .pay-method .ico{ width:2.75rem; height:2.75rem; border-radius:.8rem; display:grid; place-items:center; background:var(--cream-2); font-size:1.15rem; font-weight:800; }
  .pay-method .name{ font-weight:700; font-size:.9375rem; }
  .pay-method .kind{ font-size:.75rem; color:var(--cocoa-soft); }
  .pay-method.on{ border-color:var(--gold); background:linear-gradient(160deg, rgba(214,163,90,.14), transparent 70%); box-shadow:0 0 0 3px var(--ring); }
  .pay-method.on .ico{ background:var(--gold); color:#fff; }
  .pay-method.on .name{ color:var(--gold-deep); }
  .pay-now{ border-color:var(--gold); box-shadow:0 0 0 3px var(--ring); }
  .pay-card-btn{ margin-top:.85rem; display:flex; align-items:center; justify-content:center; gap:.6rem; flex-wrap:wrap; }
  .pay-card-btn .cards{ font-size:.7rem; letter-spacing:.08em; font-weight:800; opacity:.85; border:1px solid currentColor; border-radius:.4rem; padding:.1rem .35rem; }
  .pay-or{ display:flex; align-items:center; gap:.75rem; color:var(--cocoa-soft); font-size:.8125rem; }
  .pay-or::before, .pay-or::after{ content:''; height:1px; flex:1; background:var(--line); }
  .pay-method .tick{ position:absolute; top:.5rem; right:.55rem; width:1.35rem; height:1.35rem; border-radius:50%;
    background:#16a34a; color:#fff; font-size:.8rem; display:grid; place-items:center; }
  .pay-account{ display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-top:.75rem;
    border:1px solid var(--line); border-radius:.75rem; padding:.8rem 1rem; background:var(--cream-2); }
  .pay-account .who{ font-size:.8125rem; color:var(--cocoa-soft); margin-bottom:.25rem; }
  /* breaks only between the groups of digits, so the number reads and copies by eye */
  .pay-account .num{ font-weight:700; font-size:clamp(.95rem, 4.4vw, 1.05rem); letter-spacing:.04em; font-variant-numeric:tabular-nums; overflow-wrap:break-word; }
  .pay-copy{ flex:none; width:2.4rem; height:2.4rem; border-radius:.6rem; border:1px solid var(--line); background:var(--paper); color:var(--cocoa); font-size:1rem; }
  .pay-copy:hover{ border-color:var(--gold); color:var(--gold-deep); }
  .pay-copy.done{ border-color:#16a34a; color:#16a34a; }
  .pay-note{ font-size:.875rem; color:var(--cocoa-soft); line-height:1.6; margin-top:.75rem; }
  .pay-steps{ margin:.5rem 0 0; padding-left:1.15rem; font-size:.9rem; color:var(--cocoa-soft); line-height:1.8; }
  .pay-file{ display:block; border:1.5px dashed var(--ring); border-radius:.9rem; padding:1.1rem; text-align:center; cursor:pointer; }
  .pay-file:hover{ border-color:var(--gold); }
  .pay-file input{ display:none; }
  .pay-file .name{ font-weight:600; margin-top:.35rem; word-break:break-all; }
  .pay-sent{ border:1px solid #16a34a; border-radius:.75rem; padding:.8rem 1rem; color:#16a34a; font-weight:600; font-size:.9rem; }
  /* Letting go of an order nobody has paid for. Quiet on purpose: it sits
     next to the button that asks for money, and must not compete with it. */
  .order-drop{ margin-top:.5rem; }
  .order-drop button{ border:0; background:none; padding:.25rem 0; font-size:.8125rem;
    color:var(--cocoa-soft); text-decoration:underline; text-underline-offset:3px; }
  .order-drop button:hover{ color:var(--red, #b91c1c); }

  /* Google Pay / Apple Pay: the second button under the card one, and the
     window the gateway's own widget is shown in. */
  .pay-wallet-btn{ margin-top:.6rem; display:flex; align-items:center; justify-content:center; gap:.6rem; }
  .pay-wallet-btn[disabled]{ opacity:.6; }
  .wallet-marks{ font-weight:700; font-size:.78rem; letter-spacing:.06em; opacity:.75; }
  .pay-wallet-bad{ color:#b91c1c; font-weight:600; }
  .wallet-sheet{ position:fixed; inset:0; z-index:200; background:rgba(26,17,11,.55);
    display:flex; align-items:flex-end; justify-content:center; padding:0; }
  @media (min-width:640px){ .wallet-sheet{ align-items:center; padding:1.5rem; } }
  .wallet-box{ background:var(--paper, #fff); width:100%; max-width:30rem; border-radius:1rem 1rem 0 0;
    overflow:hidden; display:flex; flex-direction:column; max-height:92vh; }
  @media (min-width:640px){ .wallet-box{ border-radius:1rem; max-height:80vh; } }
  .wallet-top{ display:flex; align-items:center; justify-content:space-between; gap:1rem;
    padding:.85rem 1rem; border-bottom:1px solid var(--line, #e7d6be); font-weight:700; }
  .wallet-top button{ border:0; background:none; font-size:1.6rem; line-height:1; padding:0 .25rem;
    color:var(--cocoa-soft, #7a6353); cursor:pointer; }
  #wallet-frame{ border:0; width:100%; height:70vh; min-height:22rem; background:#fff; }
