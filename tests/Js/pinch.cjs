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
  var window = {
    /* Kept, not dropped: mousemove and mouseup are hung on the window,
       and the mouse is now the only pointer that slides on its own. */
    addEventListener: function(name, fn){ (ctx.listeners['win:' + name] = ctx.listeners['win:' + name] || []).push(fn); },
    nefisMapMoved: function(a, b){ ctx.calls.push(['nefisMapMoved', a, b]); }
  };
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
  globalThis.__T = { startPinch: startPinch, movePinch: movePinch,
    /* The guard that keeps a telephone's courtesy click out, and a way to
       say when the last finger was — there is no other way to put the
       clock back from a test. */
    fromFinger: fromFinger, touchedAt: function(t){ lastFinger = t; },
    state: function(){
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

  /* A mouse event, to the canvas's own listeners and to the ones hung on
     the window. */
  ctx.mouse = (type, p) => {
    const e = {
      clientX: p.x, clientY: p.y,
      preventDefault() { e.prevented = true; },
      prevented: false,
    };
    (seen[type] || []).concat(seen['win:' + type] || []).forEach((fn) => fn(e));

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

/* ---- one finger moves nothing anywhere on the box.

   It used to slide whatever window it landed on, and the owner's thumb
   kept dragging the photograph out of its window while he was only
   trying to scroll past the preview. The box is held with two fingers
   now, and one is the page's wherever it lands. */
w = build(); st = w.photos[0];
check('one finger on the photograph is left to the page', w.fire('touchstart', [ON_PHOTO]), false);
check('and begins no slide', w.T.state().dragSlot, -1, 0);
w.fire('touchmove', [{ x: ON_PHOTO.x + 40, y: ON_PHOTO.y }]);
check('so a thumb dragged across the box moves nothing', st.panX, 0, 1e-12);
w.fire('touchend', []);
check('one finger off every window leaves the page its scroll', w.fire('touchstart', [OFF_BOX]), false);
w.fire('touchend', []);
check('two fingers off every window leave the browser its zoom',
  w.fire('touchstart', grip(OFF_BOX, 60, 0)), false);
w.fire('touchend', []);

// ---- the mouse is the one pointer that still slides on its own
w = build(); st = w.photos[0];
check('a mouse on the photograph takes it', w.mouse('mousedown', ON_PHOTO), true);
w.mouse('mousemove', { x: ON_PHOTO.x + 60, y: ON_PHOTO.y });
check('and slides it', st.panX > 0, true);
w.mouse('mouseup', ON_PHOTO);
check('and lets go', w.T.state().dragSlot, -1, 0);

/* A tap is refused by nobody now, so the telephone fires a mousedown of
   its own just after one. That courtesy click must not start the very
   slide two fingers were brought in to replace. */
w = build(); st = w.photos[0];
w.fire('touchstart', [ON_PHOTO]);
w.fire('touchend', []);
check('the telephone own click after a tap starts nothing',
  w.mouse('mousedown', ON_PHOTO), false);
check('and holds nothing', w.T.state().dragSlot, -1, 0);
w.mouse('mousemove', { x: ON_PHOTO.x + 60, y: ON_PHOTO.y });
check('so it moved nothing either', st.panX, 0, 1e-12);

/* A mouse drag already in flight, and then a finger on the glass — a
   touchscreen laptop. One finger drives nothing, not even a slide
   somebody else started. */
w = build(); st = w.photos[0];
w.mouse('mousedown', ON_PHOTO);
check('the mouse is holding the photograph', w.T.state().dragSlot, 0, 0);
w.fire('touchmove', [{ x: ON_PHOTO.x + 80, y: ON_PHOTO.y }]);
check('and a lone finger does not drive it', st.panX, 0, 1e-12);
w.mouse('mouseup', ON_PHOTO);

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
  w.fire('touchstart', [ON_PHOTO], [THUMB]), false);
check('one finger on the canvas is no pinch', w.T.state().pinch, null);
w.fire('touchmove', [{ x: ON_PHOTO.x, y: ON_PHOTO.y - 60 }], [THUMB]);
check('so the photograph did not grow', st.scale, 1, 1e-12);
check('nor move', st.panY, 0, 1e-12);
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
check('and the finger left behind inherits nothing', w.T.state().dragSlot, -1, 0);

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

// ---- the map is pushed by two fingers too, and never zoomed by them
w = build(MAP);
check('one finger on the map is left to the page', w.fire('touchstart', [ON_MAP]), false);
check('and begins no map drag', w.T.state().dragMap, -1, 0);
w.fire('touchmove', [{ x: ON_MAP.x + 40, y: ON_MAP.y + 30 }]);
check('so the printed place did not move', w.mapPush.x, 0, 1e-12);
w.fire('touchend', []);
check('and the map service was never asked', w.calls.length, 0, 0);

/* Two fingers are what moves it — otherwise a telephone could not move the
   printed place at all, which is what taking the one-finger slide away
   would have cost. */
w = build(MAP);
check('two fingers on the map are taken', w.fire('touchstart', grip(ON_MAP, 150, 0)), true);
check('and it is the map they hold', w.T.state().dragMap, 0, 0);
w.fire('touchmove', grip({ x: ON_MAP.x + 40, y: ON_MAP.y + 30 }, 150, 0));
check('the push is where the middle of the hands went, x', w.mapPush.x, 40, 1e-9);
check('and y', w.mapPush.y, 30, 1e-9);
w.fire('touchend', []);
check('let go, the move was sent to the map service',
  w.calls.filter((c) => c[0] === 'dragged').length, 1, 0);
check('and the new place written down',
  w.calls.filter((c) => c[0] === 'nefisMapMoved').length, 1, 0);
check('with nothing left waiting in it', w.mapPush.x, 0, 1e-12);

/* The zoom of a street map is a fresh request to the map service, and a
   pinch would ask for new streets sixty times a second. So the hands may
   open as wide as they like over one: only where their middle went counts. */
w = build(MAP);
w.fire('touchstart', grip(ON_MAP, 150, 0));
w.fire('touchmove', grip(ON_MAP, 700, 0));
check('spreading the hands over a map moves nothing', w.mapPush.x, 0, 1e-12);
check('and asks for no new streets', w.calls.length, 0, 0);
w.fire('touchend', []);

/* A third finger brushes the box mid-push and lifts again. The change of
   grip is not itself a push, and the two that remain carry on from where
   they now are. */
w = build(MAP);
w.fire('touchstart', grip(ON_MAP, 150, 0));
w.fire('touchmove', grip({ x: ON_MAP.x + 20, y: ON_MAP.y }, 150, 0));
check('the middle moved twenty and so did the map', w.mapPush.x, 20, 1e-9);
w.fire('touchstart', grip({ x: ON_MAP.x + 120, y: ON_MAP.y }, 150, 0).concat([{ x: 150, y: 150 }]));
check('a third finger is not a hundred-pixel push', w.mapPush.x, 20, 1e-9);
w.fire('touchend', grip({ x: ON_MAP.x + 140, y: ON_MAP.y }, 150, 0));
check('nor is it lifting again', w.mapPush.x, 20, 1e-9);
w.fire('touchmove', grip({ x: ON_MAP.x + 160, y: ON_MAP.y }, 150, 0));
check('and the two that are left go on pushing', w.mapPush.x, 40, 1e-9);
w.fire('touchend', []);

/* A map slide begun with a mouse — the owner at his desk, or a touchscreen
   laptop — is finished properly when two fingers take the gesture over, not
   left in mapPush where the preview keeps drawing it and the order never
   hears of it. */
w = build({ areas: [Object.assign({}, WINDOW, { x: 20, y: 20, w: 200, h: 200 }),
                    Object.assign({}, WINDOW, { x: 500, y: 500, w: 300, h: 300 })],
  isMap: true, spot: { lat: 40.4093, lon: 49.8671, zoom: 15 },
  photo: { img: null, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, framed: false } });
w.MAP_SLOT = [true, false];
w.photos[1] = { img: IMG, scale: 1, rotate: 0, flip: false, panX: 0, panY: 0, framed: false };
w.mouse('mousedown', { x: 120, y: 120 });                   // the mouse takes the map
w.mouse('mousemove', { x: 180, y: 150 });
check('the mouse has the map', w.T.state().dragMap, 0, 0);
w.fire('touchstart', [{ x: 620, y: 620 }, { x: 680, y: 680 }]);   // two fingers on the photograph
check('the half-finished map drag was sent off, not stranded',
  w.calls.filter((c) => c[0] === 'dragged').length, 1, 0);
check('and nothing is left waiting in it', w.mapPush.x, 0, 1e-12);
check('and it is the photograph the fingers now hold', w.T.state().pinch.slot, 1, 0);

/* Two fingers pushing a map, and a cursor nudged beside them — a laptop
   whose screen is also a touchpad. The map is held through dragMap, which
   is what a mouse drag uses too, so without a guard the pointer's own
   position becomes the push. */
w = build(MAP);
w.fire('touchstart', grip(ON_MAP, 150, 0));
w.fire('touchmove', grip({ x: ON_MAP.x + 30, y: ON_MAP.y }, 150, 0));
w.mouse('mousemove', { x: 50, y: 900 });
check('a stray cursor does not join the push', w.mapPush.x, 30, 1e-9);
w.mouse('mouseup', { x: 50, y: 900 });
check('and a button released does not end it', w.T.state().dragMap, 0, 0);
w.fire('touchmove', grip({ x: ON_MAP.x + 60, y: ON_MAP.y }, 150, 0));
check('so the two fingers go on pushing', w.mapPush.x, 60, 1e-9);
w.fire('touchend', []);
check('and the push is sent off when they lift',
  w.calls.filter((c) => c[0] === 'dragged').length, 1, 0);

// ---- the courtesy-click guard, and a clock that has been put back
w = build();
w.T.touchedAt(Date.now());
check('a touch just now keeps the mouse out', w.T.fromFinger(), true);
w.T.touchedAt(Date.now() - 5000);
check('a touch five seconds ago does not', w.T.fromFinger(), false);
w.T.touchedAt(Date.now() + 3600e3);
check('nor does a clock put back an hour lock the mouse out', w.T.fromFinger(), false);

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
