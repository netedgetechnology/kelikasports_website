<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = Page::published()->where('robots_directive','index,follow')->orderBy('updated_at','desc')->get(['slug','updated_at','canonical_url']);
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>','<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
        foreach ($urls as $page) {
            $loc = $page->canonical_url ?: ($page->slug === 'home' ? url('/') : url('/'.$page->slug));
            $xml[] = '<url><loc>'.e($loc).'</loc><lastmod>'.$page->updated_at->toAtomString().'</lastmod></url>';
        }
        $xml[] = '</urlset>';
        return response(implode("\n",$xml),200,['Content-Type'=>'application/xml; charset=UTF-8']);
    }
}
