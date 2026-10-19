<?php
namespace App\Http\Controllers;
use App\Models\ContentItem;
use App\Models\Page;
use Illuminate\Http\Request;
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $validated=$request->validate(['q'=>['nullable','string','max:100']]);
        $query=trim((string)($validated['q'] ?? ''));
        $pages=collect();$items=collect();
        if($query!==''){
            $pages=Page::published()->where(function($builder)use($query){$builder->where('title','like','%'.$query.'%')->orWhere('seo_description','like','%'.$query.'%');})->orderBy('sort_order')->limit(20)->get();
            $items=ContentItem::query()->where('status','published')->whereIn('type',['sport','program','facility','event','coach','faq'])->where('title','like','%'.$query.'%')->orderBy('sort_order')->limit(20)->get();
        }
        return response()->view('public.search',compact('query','pages','items'))->header('X-Robots-Tag','noindex, follow');
    }
}
