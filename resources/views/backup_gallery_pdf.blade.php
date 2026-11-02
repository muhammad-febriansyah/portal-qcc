@extends('layoutsfront.index')
<!-- Set Title of page -->
@section('title','Search by Category')
<!-- Set Content -->
@section('content')

<!-- Top Navigation -->
@include('layoutsfront.elements.header2')
@include('layoutsfront.elements.blog-menu')

<!-- Page Content -->
<style>
    .alert {
      padding: 20px;
      background-color: #f44336;
      color: white;
    }
    .closebtn {
      margin-left: 15px;
      color: white;
      font-weight: bold;
      float: right;
      font-size: 22px;
      line-height: 20px;
      cursor: pointer;
      transition: 0.3s;
    }
    .closebtn:hover { color: black; }
    input[type=password]{
        width:100%;
        border:2px solid #aaa;
        border-radius:4px;
        margin:8px 0;
        outline:none;
        padding:8px;
        box-sizing:border-box;
        transition:.3s;
    }
    input[type=password]:focus{
        border-color:dodgerBlue;
        box-shadow:0 0 8px 0 dodgerBlue;
    }
    .card{ border-radius: 4px; background: #fff; }
    .card h3{ font-weight: 600; }
    .card img{ position: absolute; top: 20px; right: 15px; max-height: 120px; }
    @media(max-width: 990px){ .card{ margin: 20px; } }
    .button {
      background-color: #74b9ff;
      border: none;
      color: white;
      padding: 15px 32px;
      text-align: center;
      text-decoration: none;
      display: inline-block;
      font-size: 16px;
      margin: 4px 2px;
      cursor: pointer;
    }
    .button1 {width: 250px;}
    .button2 {width: 50%;}
    .button3 {width: 100%;}
</style>

<main class="main-container">
    <section class="main-highlight"></section>
    <section class="main-content">
        <div class="main-content-wrapper">
            <div class="content-body">
            @if ($message = Session::get('warning'))
                <div class="alert alert-warning alert-block">
                    <strong>{{ $message }}</strong>
                </div>
            @endif

                <div class="content-timeline">
                    <div class="post-list-header">
                        <span class="post-list-title">Document View</span>
                    </div>

                    <div class="post-lists">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                              @foreach($file_umum as $g)
                                  @if ($g->download === 0)
                                      {{-- Tidak tampilkan tombol download --}}
                                  @else
                                      <form method="get" action="{{url('storage/dokumen')}}/{{$g->lokasi}}">
                                           <!-- <button class="button" type="submit">Download!</button> -->
                                      </form>
                                  @endif

                                  <?php $encrpyt = Crypt::encryptString($g->lokasi); ?>

                                  <!-- PDF.js Viewer -->
                                  <div id="pdf-container-{{ $loop->index }}" style="width:100%; height:1000px; overflow:auto; border:1px solid #ccc;">
                                      <canvas id="pdf-render-{{ $loop->index }}"></canvas>
                                  </div>
                              @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Load PDF.js dari CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pdfjsLib = window['pdfjs-dist/build/pdf'];
    pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js";

    @foreach($file_umum as $g)
        (function(index, url){
            let pdfDoc = null,
                pageNum = 1,
                canvas = document.getElementById('pdf-render-' + index),
                ctx = canvas.getContext('2d');

            function renderPage(num) {
                pdfDoc.getPage(num).then(page => {
                    const viewport = page.getViewport({ scale: 1.5 });
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const renderCtx = {
                        canvasContext: ctx,
                        viewport: viewport
                    };
                    page.render(renderCtx);
                });
            }

            pdfjsLib.getDocument(url).promise.then(pdf => {
    	    pdfDoc = pdf;

	    // Loop semua halaman
    	    for (let num = 1; num <= pdf.numPages; num++) {
            pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale: 1.5 });

            // Buat canvas baru untuk tiap halaman
            const canvas = document.createElement('canvas');
            canvas.classList.add('pdf-page');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            document.getElementById('pdf-container-' + index).appendChild(canvas);

            const ctx = canvas.getContext('2d');
            const renderCtx = {
                canvasContext: ctx,
                viewport: viewport
            };
            page.render(renderCtx);

            // Disable klik kanan di tiap canvas
            canvas.addEventListener('contextmenu', e => e.preventDefault());
            });
           }
         });

            // Disable klik kanan luar area pdf
            canvas.addEventListener('contextmenu', e => e.preventDefault());
        })({{ $loop->index }}, "{{ url('storage/dokumen/' . $g->lokasi) }}");
    @endforeach
});
</script>

<script>
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    e.stopPropagation();
}, true); // gunakan capture = true
</script>

@endsection
