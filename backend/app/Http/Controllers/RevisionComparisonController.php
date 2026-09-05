<?php

namespace App\Http\Controllers;

use App\Models\ResourceRevision;
use App\Services\RevisionComparisonService;
use Illuminate\Http\Request;

final class RevisionComparisonController extends Controller
{
    public function __construct(private RevisionComparisonService $comparisons) {}

    public function compare(Request $request,string $type,string $resource)
    {
        $data=$request->validate(['from_revision_id'=>['required','integer'],'to_revision_id'=>['required','integer'],'focus'=>['nullable','string','max:150']]);
        return $this->comparisons->compareIds($request->user(),$type,$resource,$data['from_revision_id'],$data['to_revision_id'],$data['focus']??null);
    }

    public function previous(Request $request,string $type,string $resource,string $revision)
    {
        return $this->comparisons->previousId($request->user(),$type,$resource,$revision);
    }
}