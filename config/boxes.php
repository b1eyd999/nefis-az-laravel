<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Box canvas
    |--------------------------------------------------------------------------
    |
    | Every box is designed at this size; the owner's reference visuals are
    | exported at it too, so a layer placed in the editor lands exactly where
    | it sits on the visual.
    |
    */

    'canvas' => ['width' => 969, 'height' => 1895],

    /*
    |--------------------------------------------------------------------------
    | Customer-facing scenes
    |--------------------------------------------------------------------------
    |
    | The finished box is shown to customers set into these photographed
    | chocolate-bar scenes. They are a stand-in: the owner means to design a
    | new mockup for this, and it will replace this list.
    |
    | cx/cy is where the box's centre falls on the scene, scale how many scene
    | pixels one canvas pixel takes, rotation the tilt of the bar's face.
    | The images live in public/scenes — part of the site, not of any design,
    | so they do not depend on the design storage.
    |
    */

    'scenes' => [
        ['label' => 'Ön görünüş', 'background' => 'scenes/bar-scene-diagonal.webp',
            'width' => 1400, 'height' => 1750, 'cx' => 677, 'cy' => 880.5, 'scale' => 0.5623, 'rotation' => -3],
        ['label' => 'Əyri bucaq', 'background' => 'scenes/bar-scene-tilt.webp',
            'width' => 1400, 'height' => 1750, 'cx' => 684, 'cy' => 894.5, 'scale' => 0.5623, 'rotation' => -47],
        ['label' => 'Yan görünüş', 'background' => 'scenes/bar-scene-front.webp',
            'width' => 1400, 'height' => 1750, 'cx' => 690, 'cy' => 898.5, 'scale' => 0.5623, 'rotation' => -65],
        ['label' => 'Dik profil', 'background' => 'scenes/bar-scene-upright.webp',
            'width' => 1400, 'height' => 1750, 'cx' => 679, 'cy' => 870.5, 'scale' => 0.5623, 'rotation' => 25],
    ],

];
