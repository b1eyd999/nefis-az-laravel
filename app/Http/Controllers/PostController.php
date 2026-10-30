<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The blog: the list of articles, and one page per article.
 *
 * Written for the questions people ask before they order — which photograph
 * prints well, what to write on the lid, how a box is actually made — in the
 * three languages the shop speaks, each article belonging to one of them.
 */
class PostController extends Controller
{
    public function index(): View
    {
        $locale = Locale::current();
        $posts = Post::shown()->inLocale($locale)->get();

        // The blog exists at a fixed address in each language, so the
        // layout's own hreflang loop is right for this page.
        return view('blog.index', compact('posts', 'locale'));
    }

    public function show(Post $post): View|RedirectResponse
    {
        $locale = Locale::current();
        abort_unless($post->isShown(), 404);

        /* An article's slug is a word in its own language, but the route
           finds a row by slug whatever language the address carries — so
           /en/blog/sekil-secimi resolves to the Azerbaijani article. It
           names a page that does exist, so the answer is where it lives
           rather than a dead end; the model falls back to that language's
           blog when no twin is written. */
        if ($post->locale !== $locale) {
            return redirect($post->localisedUrl($locale), 301);
        }

        // The database matches a slug whatever its case, so /bloq/SEKIL
        // answers as well as the real address. One of them is the page.
        if ((string) request()->route()->originalParameter('post') !== $post->slug) {
            return redirect($post->url(), 301);
        }

        $products = $post->shownProducts();
        $others = Post::shown()->inLocale($locale)->whereKeyNot($post->id)->take(3)->get();

        return view('blog.show', compact('post', 'products', 'others'));
    }
}
