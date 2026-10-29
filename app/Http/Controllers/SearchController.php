<?php
namespace App\Http\Controllers;
use App\Models\ContentItem;
use App\Models\Page;
use App\Support\Like;
use Illuminate\Http\Request;
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $validated=$request->validate(['q'=>['nullable','string','max:100']]);
        $query=trim((string)($validated['q'] ?? ''));
        $pages=collect();$items=collect();
        if($query!==''){
            // What visitors type is matched literally: "%" and "_" never widen the results (App\Support\Like).
            $pages=Like::anyColumn(Page::published(),['title','seo_description'],$query)->orderBy('sort_order')->limit(20)->get();
            $items=Like::anyColumn(ContentItem::query()->where('status','published')->whereIn('type',['sport','program','facility','event','coach','faq']),['title'],$query)->orderBy('sort_order')->limit(20)->get()->reject(fn ($item) => (bool) ($item->data['demo'] ?? false))->values();
        }
        return response()->view('public.search',compact('query','pages','items'))->header('X-Robots-Tag','noindex, follow');
    }
}
