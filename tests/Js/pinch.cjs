/**
 * Two fingers on the box preview, checked against the real code.
 *
 * The gesture lives in customize.blade.php and cannot be reached from PHP,
 * so this lifts the whole drag-and-pinch block straight out of the view,
 * gives it the handful of things it leans on, and then **fires real touch
 * events at the real listeners**. Nothing is copied: a change to the
 * arithmetic or to the event wiring in the view is a change to what is
 * tested here.
 *
 * The first half checks the one property that decides whether a pinch feels
 * like a pinch — the piece of photograph between the two fingers stays
 * between them. The second half checks everything the first half cannot
 * see, because a review found three live faults hiding behind exactly the
 * stubs an arithmetic-only harness needs: a pinch on a street-map window
 * walking the printed address down the road, a thumb resting on the page
 * being adopted as the second finger, and a stray third finger killing the
 * gesture outright.
 *
 * Run by tests/Feature/PinchToZoomTest.php, or on its own:
 *   node tests/Js/pinch.cjs
 */
const fs = require('fs');
const path = require('path');

const VIEW = path.join(__dirname, '..', '..', 'resources', 'views', 'products', 'customize.blade.php');
const lines = fs.readFileSync(VIEW, 'utf8').split(/\r?\n/);

const from = lines.findIndex((l) => /drag to reposition/.test(l));
const to = lines.findIndex((l, i) => i > from && /^\}\)\(\);/.test(l));
if (from < 0 || to < 0) {
  console.error('the drag-and-pinch block is no longer where this test looks for it');
  process.exit(2);
}
const block = lines.slice(from, to).join('\n');
for (const must of ['function startPinch', 'function movePinch', "addEventListener('touchstart'", "addEventListener('touchmove'"]) {
  if (!block.includes(must)) {
    console.error('the extracted block is missing ' + must + ' — the boundaries have moved');
    process.exit(2);
  }
}

/* A window 400 x 300 at (100, 200) of a 1000 x 1000 design, and a photograph
   800 x 600. None of the numbers is square, so an x and a y that have been
   swapped cannot pass unnoticed. */
const WINDOW = { x: 100, y: 200, w: 400, h: 300, rotation: 0, shape: 'rectangle' };
const IMG = { width: 800, height: 600 };
const DESIGN = { w: 1000, h: 1000 };

/**
 * The block, wired to a canvas whose listeners we keep, so a test can press
 * fingers onto it. `world` says what kind of box this is.
 */
function build(world) {
  const w = Object.assign({
    areas: [Object.assign({}, WINDOW)],
    photo: { img: IMG, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, faceBox: null, framed: false },
    isMap: false,
    spot: null,
    offDesign: null,          // a predicate: is this canvas point off the design?
  }, world || {});

  const seen = {};            // the listeners the block registered
  const calls = [];           // what it asked the rest of the shop to do
  const ctx = {
    listeners: seen,
    calls,
    mapPush: { x: 0, y: 0, drawn: 2.5 },
    photos: [w.photo],
    MAP_SLOT: [w.isMap],
    spot: w.spot,
    areas: w.areas,
    offDesign: w.offDesign,
    draws: 0,
  };

  const harness = `
(function(){
  var ctx = globalThis.__ctx;
  var logicalW = ${DESIGN.w}, logicalH = ${DESIGN.h};
  var canvas = {
    getBoundingClientRect: function(){ return { left: 0, top: 0, width: ${DESIGN.w}, height: ${DESIGN.h} }; },
    addEventListener: function(name, fn){ (ctx.listeners[name] = ctx.listeners[name] || []).push(fn); }
  };
  var window = { addEventListener: function(){}, nefisMapMoved: function(a, b){ ctx.calls.push(['nefisMapMoved', a, b]); } };
  var document = { querySelector: function(){ return null; } };
  var photos = ctx.photos, MAP_SLOT = ctx.MAP_SLOT, spot = ctx.spot, mapPush = ctx.mapPush;
  window.NefisStreetMap = {
    dragged: function(lat, lon, zoom, dx, dy, drawn){
      ctx.calls.push(['dragged', dx, dy, drawn]);
      return { lat: lat + dy / 1000, lon: lon + dx / 1000 };
    }
  };
  /* The real toMockupCoords, through the real toCanvasPixel: a view that is
     a photograph of the box hands back null off the design itself. */
  var NefisScene = { designPoint: function(scene, x, y){
    return ctx.offDesign && ctx.offDesign(x, y) ? null : { x: x, y: y };
  } };
  function currentAngle(){ return { areas: ctx.areas, tw: logicalW, th: logicalH, scene: ctx.offDesign ? {} : null, bg: null }; }
  function areaFor(i){ return currentAngle().areas[i] || null; }
  function toolsOf(){ return null; }
  function draw(){ ctx.draws++; }
${block}
  globalThis.__T = { startPinch: startPinch, movePinch: movePinch, state: function(){
    return { pinch: pinch, dragSlot: dragSlot, dragMap: dragMap }; } };
})();
`;

  globalThis.__ctx = ctx;
  new Function(harness)();
  ctx.T = globalThis.__T;

  /* A touch event as a browser builds it. `on` is what is touching the
     canvas; `off` is a contact that began somewhere else on the page and so
     appears in touches but never in targetTouches. */
  ctx.fire = (type, on, off) => {
    const pt = (p, i) => ({ identifier: i, clientX: p.x, clientY: p.y });
    const e = {
      targetTouches: on.map(pt),
      touches: on.concat(off || []).map(pt),
      changedTouches: [],
      preventDefault() { e.prevented = true; },
      prevented: false,
    };
    (seen[type] || []).forEach((fn) => fn(e));

    return e.prevented;
  };

  return ctx;
}

let bad = 0;
const check = (name, got, want, tol) => {
  // An exact comparison when no tolerance is given, never `<= undefined`,
  // which is false for every number and fails a test that in fact passed.
  const ok = typeof want === 'boolean' || want === null
    ? got === want
    : Math.abs(got - want) <= (tol || 0);
  if (!ok) bad++;
  console.log((ok ? 'ok   ' : 'FAIL ') + name + '  (' + JSON.stringify(got) + ' vs ' + JSON.stringify(want) + ')');
};

/** Which point of the photograph is sitting under this point of the box. */
function imagePointAt(m, state, area) {
  area = area || WINDOW;
  const isEllipse = area.shape === 'ellipse';
  const rad = ((isEllipse ? 0 : area.rotation) + (state.rotate || 0)) * Math.PI / 180;
  const s = Math.max(area.w / IMG.width, area.h / IMG.height) * state.scale;
  const cx = area.x + area.w / 2 + state.panX * area.w;
  const cy = area.y + area.h / 2 + state.panY * area.h;
  const dx = m.x - cx, dy = m.y - cy;
  let ux = dx * Math.cos(-rad) - dy * Math.sin(-rad);
  const uy = dx * Math.sin(-rad) + dy * Math.cos(-rad);
  if (state.flip) ux = -ux;

  return { x: ux / s, y: uy / s };
}

/** Two fingers that many pixels apart, at that angle, about that point. */
const grip = (m, span, deg) => {
  const r = span / 2, a = deg * Math.PI / 180;

  return [
    { x: m.x - r * Math.cos(a), y: m.y - r * Math.sin(a) },
    { x: m.x + r * Math.cos(a), y: m.y + r * Math.sin(a) },
  ];
};
const asTouches = (pts) => pts.map((p) => ({ clientX: p.x, clientY: p.y }));

console.log('--- the arithmetic: what is between the fingers stays between them');

// ---- the picture grows by as much as the hands open
let w = build();
let st = w.photos[0];
let mid = { x: 180, y: 260 };                     // well off the window's centre
w.T.startPinch(asTouches(grip(mid, 120, 0)));
let held = imagePointAt(mid, st);
for (const span of [140, 170, 200, 240]) w.T.movePinch(asTouches(grip(mid, span, 0)));
check('120 to 240 apart doubles the picture', st.scale, 2, 1e-9);
check('and what was between the fingers is still there, x', imagePointAt(mid, st).x, held.x, 0.5);
check('and what was between the fingers is still there, y', imagePointAt(mid, st).y, held.y, 0.5);
check('a straight pinch does not turn it', st.rotate, 0, 1e-9);

// ---- a tilted window, a mirrored photograph, and a hand that slides as it opens
const tilted = Object.assign({}, WINDOW, { rotation: 18 });
w = build({ areas: [tilted], photo: { img: IMG, scale: 1.4, rotate: -25, flip: true, panX: 0.1, panY: -0.05, framed: false } });
st = w.photos[0];
mid = { x: 420, y: 300 };
w.T.startPinch(asTouches(grip(mid, 200, 30)));
held = imagePointAt(mid, st, tilted);
for (let i = 0; i < 6; i++) {
  mid = { x: mid.x + 7, y: mid.y - 4 };
  w.T.movePinch(asTouches(grip(mid, 200 + 25 * (i + 1), 30)));
}
check('the scale follows the hands here too', st.scale, 1.4 * 350 / 200, 1e-9);
check('the picture went with the hands, x', imagePointAt(mid, st, tilted).x, held.x, 1);
check('the picture went with the hands, y', imagePointAt(mid, st, tilted).y, held.y, 1);
check('and a tilted window did not turn it either', st.rotate, -25, 1e-9);

// ---- the dead angle: a wobble is not a turn, and the turn starts where it ends
w = build(); st = w.photos[0]; mid = { x: 300, y: 350 };
w.T.startPinch(asTouches(grip(mid, 200, 0)));
w.T.movePinch(asTouches(grip(mid, 200, 6)));
check('six degrees of wobble is ignored', st.rotate, 0, 1e-9);
w.T.movePinch(asTouches(grip(mid, 200, 9.9)));
check('and so is nine and a bit', st.rotate, 0, 1e-9);
w.T.movePinch(asTouches(grip(mid, 200, 25)));
check('twenty-five turns by fifteen, not by twenty-five', st.rotate, 15, 1e-6);
w.T.movePinch(asTouches(grip(mid, 200, 40)));
check('and follows the fingers from there', st.rotate, 30, 1e-6);
w.T.movePinch(asTouches(grip(mid, 200, 20)));
check('and back the other way', st.rotate, 10, 1e-6);

// ---- the twist holds the point between the fingers as well
w = build(); st = w.photos[0]; mid = { x: 200, y: 250 };
w.T.startPinch(asTouches(grip(mid, 200, 0)));
w.T.movePinch(asTouches(grip(mid, 200, 20)));     // breaks the dead angle
held = imagePointAt(mid, st);
for (const d of [40, 60, 80]) w.T.movePinch(asTouches(grip(mid, 200, d)));
check('turned by what the fingers turned', st.rotate, 70, 1e-6);
check('and the held point survived the twist, x', imagePointAt(mid, st).x, held.x, 1);
check('and the held point survived the twist, y', imagePointAt(mid, st).y, held.y, 1);

// ---- the slider's own ends, and no creeping once it is against them
w = build(); st = w.photos[0]; mid = { x: 250, y: 300 };
w.T.startPinch(asTouches(grip(mid, 100, 0)));
for (const span of [300, 600, 900, 1400]) w.T.movePinch(asTouches(grip(mid, span, 0)));
check('stops where the slider stops, 500%', st.scale, 5, 1e-9);
const stuck = { x: st.panX, y: st.panY };
w.T.movePinch(asTouches(grip(mid, 2000, 0)));
check('and then the picture does not creep, x', st.panX, stuck.x, 1e-12);
check('and then the picture does not creep, y', st.panY, stuck.y, 1e-12);
w.T.startPinch(asTouches(grip(mid, 1000, 0)));
for (const span of [400, 150, 60, 20]) w.T.movePinch(asTouches(grip(mid, span, 0)));
check('and at the other end, 50%', st.scale, 0.5, 1e-9);

/* A window that frames the face by itself is not bound by the slider:
   frameOnFace writes desiredScale / baseScale with no clamp, and a small
   head in a big photograph comes out at six or ten. Clamping that down here
   would shrink the head on the first graze and the slider could not reach
   back up past five. */
w = build({ photo: { img: IMG, scale: 8.4, rotate: 0, flip: false, panX: 0, panY: 0, framed: false } });
st = w.photos[0]; mid = { x: 300, y: 350 };
w.T.startPinch(asTouches(grip(mid, 200, 0)));
w.T.movePinch(asTouches(grip(mid, 200.2, 0)));    // a graze, no intent to zoom
check('a face framed above the slider is not shrunk by a graze', st.scale, 8.4, 0.02);
w.T.movePinch(asTouches(grip(mid, 100, 0)));      // but it can still be pinched smaller
check('and can still be made smaller', st.scale, 4.2, 0.02);
w.T.movePinch(asTouches(grip(mid, 400, 0)));
check('and not back above the slider once it is under it', st.scale, 5, 1e-9);

// ---- past the far end of the turn slider
w = build({ photo: { img: IMG, scale: 1, rotate: 170, flip: false, panX: 0, panY: 0, framed: false } });
st = w.photos[0]; mid = { x: 300, y: 300 };
w.T.startPinch(asTouches(grip(mid, 200, 150)));
w.T.movePinch(asTouches(grip(mid, 200, 165)));    // breaks the dead angle: +5
check('the dead angle applies here too', st.rotate, 175, 1e-6);
w.T.movePinch(asTouches(grip(mid, 200, -170)));   // the fingers pass straight up
check('a turn past 180 wraps instead of leaping 360', st.rotate, -160, 1e-6);
w.T.movePinch(asTouches(grip(mid, 200, 170)));
/* -180 and 180 are the same angle and the slider takes either; the step
   lands exactly on the cut, so compare as an angle and not as a number. */
check('and back across', Math.abs(Math.abs(st.rotate) - 180), 0, 1e-6);

console.log('\n--- the fingers: what the listeners do with them');

const ON_PHOTO = { x: 300, y: 350 };              // inside the window
const OFF_BOX = { x: 900, y: 900 };               // outside every window

// ---- one finger on the photograph slides it; one finger elsewhere is the page's
w = build(); st = w.photos[0];
check('one finger on the photograph is taken', w.fire('touchstart', [ON_PHOTO]), true);
w.fire('touchmove', [{ x: ON_PHOTO.x + 40, y: ON_PHOTO.y }]);
check('and slid it', st.panX > 0, true);
w.fire('touchend', []);
check('one finger off every window leaves the page its scroll', w.fire('touchstart', [OFF_BOX]), false);
w.fire('touchend', []);
check('two fingers off every window leave the browser its zoom',
  w.fire('touchstart', grip(OFF_BOX, 60, 0)), false);
w.fire('touchend', []);

// ---- two fingers on the photograph pinch it
w = build(); st = w.photos[0];
check('two fingers on the photograph are taken', w.fire('touchstart', grip(ON_PHOTO, 100, 0)), true);
w.fire('touchmove', grip(ON_PHOTO, 200, 0));
check('and doubled it', st.scale, 2, 1e-9);
w.fire('touchend', []);
check('and let go cleanly', w.T.state().pinch, null);

/* A thumb left resting on the page below the box — on the slider it was
   just dragging — is not the second finger of anything. It is in touches
   but not in targetTouches, and its touchend would never reach this canvas
   at all, so a pinch built on it could never end. */
w = build(); st = w.photos[0];
const THUMB = { x: 190, y: 980 };
check('a contact that began elsewhere is not adopted',
  w.fire('touchstart', [ON_PHOTO], [THUMB]), true);
check('it is a one-finger slide, not a pinch', w.T.state().pinch, null);
w.fire('touchmove', [{ x: ON_PHOTO.x, y: ON_PHOTO.y - 60 }], [THUMB]);
check('so the photograph did not grow', st.scale, 1, 1e-12);
w.fire('touchend', [], [THUMB]);

/* A third finger of the holding hand brushes the box and lifts again. Two
   are still pinching and the gesture must survive it. */
w = build(); st = w.photos[0];
w.fire('touchstart', grip(ON_PHOTO, 100, 0));
w.fire('touchstart', grip(ON_PHOTO, 100, 0).concat([{ x: 320, y: 500 }]));
check('a third finger does not end the pinch', !!w.T.state().pinch, true);
w.fire('touchend', grip(ON_PHOTO, 100, 0));
check('and neither does it lifting again', !!w.T.state().pinch, true);
w.fire('touchmove', grip(ON_PHOTO, 200, 0));
check('the two that are left still pinch', st.scale, 2, 1e-9);
w.fire('touchend', [grip(ON_PHOTO, 100, 0)[0]]);
check('down to one finger, the pinch is over', w.T.state().pinch, null);
check('and that finger carries on sliding', w.T.state().dragSlot, 0);

// ---- touchcancel puts everything down
w = build();
w.fire('touchstart', grip(ON_PHOTO, 100, 0));
w.fire('touchcancel', []);
check('a cancelled touch ends the pinch', w.T.state().pinch, null);
check('and the slide with it', w.T.state().dragSlot, -1);

console.log('\n--- the street map: a place the box gets printed with');

const MAP = { areas: [Object.assign({}, WINDOW, { w: 820, h: 820, x: 90, y: 90 })], isMap: true,
  photo: { img: null, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, framed: false },
  spot: { lat: 40.4093, lon: 49.8671, zoom: 15 } };
const ON_MAP = { x: 500, y: 500 };

// one finger still drags the map — which is what the touchmove change was for
w = build(MAP);
check('one finger is taken by the map', w.fire('touchstart', [ON_MAP]), true);
w.fire('touchmove', [{ x: ON_MAP.x + 40, y: ON_MAP.y + 30 }]);
w.fire('touchend', []);
check('and the move was sent to the map service',
  w.calls.filter((c) => c[0] === 'dragged').length, 1, 0);
check('and the new place written down',
  w.calls.filter((c) => c[0] === 'nefisMapMoved').length, 1, 0);

/* Two fingers on a map window are a request to magnify the design, not to
   move the address. Reading them as one finger walked the printed place
   hundreds of metres down the road, once per pinch.

   Two separate things stop it and either one would be enough, so each is
   pinned on its own below before the fault itself is reproduced. */
w = build(MAP);
w.fire('touchstart', [ON_MAP]);
check('one finger has the map', w.T.state().dragMap, 0, 0);
w.fire('touchstart', grip(ON_MAP, 150, 0));
check('and lets go the moment a second finger says this is no slide',
  w.T.state().dragMap, -1, 0);
w.fire('touchend', []); w.fire('touchend', []);

/* And the second lock: a move carrying two fingers is never a one-finger
   slide, whatever state was left lying about. */
w = build(MAP);
w.fire('touchstart', [ON_MAP]);
w.fire('touchmove', grip(ON_MAP, 400, 0));          // no touchstart in between
check('a two-finger move is not read as a slide', w.mapPush.x, 0, 1e-12);
w.fire('touchend', []);

w = build(MAP);
w.fire('touchstart', [ON_MAP]);                             // the first finger lands
check('two fingers on the map are left to the browser',
  w.fire('touchstart', grip(ON_MAP, 150, 0)), false);
w.fire('touchmove', grip(ON_MAP, 400, 0));
w.fire('touchmove', grip(ON_MAP, 700, 0));
check('and the spread moved nothing', w.mapPush.x, 0, 1e-12);
w.fire('touchend', []);
w.fire('touchend', []);
check('so the printed place is where he put it',
  w.calls.filter((c) => c[0] === 'nefisMapMoved').length, 0, 0);

/* A map window and a photograph window on the same box: a half-finished map
   drag must be sent off when the pinch takes the gesture over, not left in
   mapPush where the preview keeps drawing it and the order never hears of
   it. */
w = build({ areas: [Object.assign({}, WINDOW, { x: 20, y: 20, w: 200, h: 200 }),
                    Object.assign({}, WINDOW, { x: 500, y: 500, w: 300, h: 300 })],
  isMap: true, spot: { lat: 40.4093, lon: 49.8671, zoom: 15 },
  photo: { img: null, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, framed: false } });
w.MAP_SLOT = [true, false];
w.photos[1] = { img: IMG, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, framed: false };
w.fire('touchstart', [{ x: 120, y: 120 }]);                 // a finger on the map
w.fire('touchmove', [{ x: 180, y: 150 }]);
w.fire('touchstart', [{ x: 120, y: 120 }, { x: 650, y: 650 }]);  // a second on the photograph
check('the half-finished map drag was sent off, not stranded',
  w.calls.filter((c) => c[0] === 'dragged').length, 1, 0);
check('and nothing is left waiting in it', w.mapPush.x, 0, 1e-12);

console.log('\n--- a view that is a photograph of the box');

/* On such a view toMockupCoords hands back null off the design itself — a
   finger on the table beside the box. There is then nowhere to anchor the
   zoom, and growing the picture anyway slides it out from under the hand. */
w = build({ offDesign: (x) => x > 600 });
st = w.photos[0];
w.fire('touchstart', [ON_PHOTO, { x: 400, y: 350 }]);
check('a pinch inside the design starts', !!w.T.state().pinch, true);
w.fire('touchmove', [ON_PHOTO, { x: 500, y: 350 }]);
const onDesign = st.scale;
check('and works while both hands are on it', onDesign > 1, true);
held = imagePointAt({ x: (ON_PHOTO.x + 500) / 2, y: 350 }, st);
w.fire('touchmove', [ON_PHOTO, { x: 1400, y: 350 }]);       // midpoint now off the design
check('a hand off the design does not scale blindly', st.scale, onDesign, 1e-12);
w.fire('touchmove', [ON_PHOTO, { x: 500, y: 350 }]);        // and back on
check('and the gesture picks up where it left off', st.scale, onDesign, 1e-9);

console.log(bad === 0 ? '\nall good' : '\n' + bad + ' failed');
process.exit(bad ? 1 : 0);
