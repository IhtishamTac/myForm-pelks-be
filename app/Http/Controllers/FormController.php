<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\User;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Response;
use App\Models\AllowedDomain;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $forms = Form::where('creator_id',auth()->id())->get();

        return response()->json([
            'message' => 'Get all forms success',
            'forms' => $forms
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'slug' => 'required|unique:forms,slug|regex:/^[a-zA-Z0-9.-]+$/',
            'allowed_domains' => 'array',
            'description' => '',
            'limit_one_response' => '',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors()
            ], 422);
        }

        $form = new Form();
        $form->name = $request->name;
        $form->slug = $request->slug;
        $form->description = $request->description;
        $form->limit_one_response = $request->limit_one_response;
        $form->creator_id = auth()->id();
        $form->save();

        if ($form) {
            foreach ($request->allowed_domains as $adomains) {
                $adomain = new AllowedDomain();
                $adomain->form_id = $form->id;
                $adomain->domain = $adomains;
                $adomain->save();
            }
        }

        return response()->json([
            'message' => 'Create form success',
            'form' => $form
        ],200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $slug)
    {
        $form = Form::where('slug', $slug)->with(['allowed_domains', 'questions'])->first();
        if(!$form){
            return response()->json([
                'message' => 'Form not found',
            ],404);
        }
        $userDomain = substr(strrchr(auth()->user()->email, '@'), 1);
        $allowedDomains = $form->allowed_domains->pluck('domain')->toArray();
        if(!in_array($userDomain, $allowedDomains)){
            return response()->json([
                'message' => 'Forbidden access',
            ],403);
        }
        return response()->json([
            'message' => 'Get form success',
            'form' => $form
        ],200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function addQuestion(Request $request, string $slug)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'choice_type' => 'required|in:short answer,paragraph,date,multiple choice,dropdown,checkboxes',
            'choices' => 'array|required_if:choice_type,multiple choice,dropdown,checkboxes',
            'is_required' => '',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors()
            ], 422);
        }
        $form = Form::where("slug", $slug)->first();

        if(!$form){
            return response()->json([
                'message' => 'Form not found',
            ],404);
        }

        $question = new Question();
        $question->name = $request->name;
        $question->choice_type = $request->choice_type;
        $question->is_required = $request->is_required;
        $question->choices = $jsonArray = json_encode($request->choices);
        if(array($jsonArray)){
            $question->choices = implode(',',$request->choices);
        }
        $question->form_id = $form->id;
        $question->save();

        return response()->json([
            'message' => 'Add question success',
            'question' => $question
        ],200);
    }

    public function remQuestion(string $slug, string $question_id){
        $form = Form::where("slug", $slug)->first();
        if(!$form){
            return response()->json([
                'message' => 'Form not found',
            ],404);
        }
        if($form->creator_id != auth()->id()){
            return response()->json([
                'message' => 'Forbidden access',
            ],403);
        }
        $question = Question::where(["form_id"=> $form->id, "id"=> $question_id])->first();
        if(!$question){
            return response()->json([
                'message' => 'Question not found',
            ],404);
        }
        if($question->delete()){
            return response()->json([
                'message' => 'Remove question success',
            ],200);
        }
    }

    public function submitResponse(Request $request, string $slug){
        $validator = Validator::make($request->all(), [
            'answers' => [
                'question_id' => 'required_if:questions,is_required,true',
                'value' => 'required_if:questions,is_required,true',
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid field',
                'errors' => $validator->errors()
            ], 422);
        }
        $form = Form::where("slug", $slug)->first();

        // if($form->limit_one_response === 1){
        //     if(Response::where('user_id', auth()->id())){
        //         return response()->json([
        //             'message' => 'You can not submit form twice',
        //         ], 422);
        //     }
        // }

        $userDomain = substr(strrchr(auth()->user()->email, '@'), 1);
        $allowedDomains = $form->allowed_domains->pluck('domain')->toArray();
        if(!in_array($userDomain, $allowedDomains)){
            return response()->json([
                'message' => 'Forbidden access',
            ],403);
        }

        $response = new Response();
        $response->form_id = $form->id;
        $response->user_id = auth()->id();
        $response->date = Carbon::now();
        $response->save();

        if($response){
            foreach($request->answers as $ans){
                $answer = new Answer();
                $answer->response_id = $response->id;
                $answer->question_id = $ans['question_id'];
                $answer->value = $ans['value'];
                $answer->save();

                return response()->json([
                    'message' => 'Submit response success',
                ],200);
            }
        }
    }

    public function getAllResponses(string $slug){
        $form = Form::where("slug", $slug)->first();
        if(!$form){
            return response()->json([
                'message' => 'Form not found',
            ],404);
        }
        if($form->creator_id != auth()->id()){
            return response()->json([
                'message' => 'Forbidden access',
            ],403);
        }
        $responses = Response::where('user_id', auth()->id())->with(['user', 'answer'])->get();

        $questionArray = Question::where('form_id', $form->id)->get();
        $questionName = $questionArray->pluck('name');
        if($questionName->count() <= 0){
            return;
        }
        foreach($questionName as $qn){
            $keyNames[] = $qn;
        }

        $newResponses = [];
        foreach($responses as $res){
            $newAnswers = [];
            foreach($res->answer as $index => $answer){
                $newAnswers[$keyNames[$index]] = $answer['value'];
            }

            $newResponses[] = [
                'date' => $res->date,
                'user' => $res->user,
                'answers' => $newAnswers
            ];
        }

        $responses->makeHidden(['form_id', 'user_id']);
        return response()->json([
            'message' => 'Get responses success',
            'responses' => $newResponses
        ],200);
    }
}
