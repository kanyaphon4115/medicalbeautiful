@extends('layouts.app')
@section('title','Write a Review | AESTHETIC')
@section('body-class','patient-reviews-page')
@section('content')
<section class="pr-form-page"><a class="pr-write" href="{{ route('reviews.index') }}">← Patient Reviews</a><button type="button" class="pr-write" data-open-review>Write a Review</button></section>
@include('reviews.write-dialog')
@endsection