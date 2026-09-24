<?php

use App\Support\Locale;

if (! function_exists('lroute')) {
    /**
     * A link that stays in the language the page is written in: on /ru/cart
     * "lroute('cart.add')" gives /ru/cart, on the Azerbaijani site /cart.
     */
    function lroute(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        $localized = Locale::route($name);

        return route(app('router')->has($localized) ? $localized : $name, $parameters, $absolute);
    }
}
