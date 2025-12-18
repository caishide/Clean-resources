@extends($activeTemplate . 'layouts.app')
@section('panel')
    <section class="py-5 my-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <img class="w-100" src="{{ getImage(getFilePath('maintenance') . '/' . @$maintenance->data_values->image, getFileSize('maintenance')) }}" alt="image">
                    <br>
                    <br>
                    <div>
                        @php echo $maintenance->data_values->description @endphp
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection