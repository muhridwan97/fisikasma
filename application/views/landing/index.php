<div id="carousel-wrap">
  <div id="carousel-wrap-in" class="container">
    <div id="carousel-example-generic" class="carousel slide" data-ride="carousel">
      <!-- Indicators -->
      <ol class="carousel-indicators">
        <?php foreach ($banners as $key => $banner) : ?>
          <?php if ($key == 0) : ?>
            <li data-target="#carousel-example-generic" data-slide-to="0" class="active"></li>
          <?php else : ?>
            <li data-target="#carousel-example-generic" data-slide-to="<?= $key ?>"></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>

      <!-- Wrapper for slides -->
      <div class="carousel-inner slider-post">
        <?php foreach ($banners as $key => $banner) : ?>
          <div class="item <?= $key == 0 ? 'active' : '' ?>">
            <img src="<?= asset_url($banner['photo']) ?>" alt="...">
            <div class="carousel-caption">
              <h2><?= $banner['title'] ?></h2>
              <p style="font-family: myf">
                <?= $banner['caption'] ?>
              </p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Controls -->
      <a class="left carousel-control" href="#carousel-example-generic" data-slide="prev">
        <span class="glyphicon glyphicon-chevron-left"></span>
      </a>
      <a class="right carousel-control" href="#carousel-example-generic" data-slide="next">
        <span class="glyphicon glyphicon-chevron-right"></span>
      </a>
    </div>
  </div>
</div>
</div>
<div id="popular-wrap">
</div>
<div id="popular-wrap-bottom">
  <h2 class="title-section">POPULAR POST</h2>
  <div class="underscore"></div>
  <div class="container">

    <?php foreach ($blogPopulars as $key => $blogPopular) : ?>
      <div data-aos="fade-up">
        <div class="col-sm-4 post-populer">
          <img class="img-responsive img-popular" src="<?= asset_url($blogPopular['photo']) ?>">
          <h3>
            <a href="<?= base_url('landing/blog-view/' . $blogPopular['id']) ?>" class="title-post-popular"><?= $blogPopular['title'] ?></a>
          </h3>
          <h6 style="color:#555;font-family: myf">Penulis : <b><?= $blogPopular['writer_name'] ?></b><span style="margin-left:10px">
              Tanggal : <b><?= format_date($blogPopular['date'], 'd F Y') ?></b></span></h6>
          <p class="content-popular-post">
            <?= substr(strip_tags($blogPopular['body']), 0, 110) . "..." ?>
          </p>
          <a href="<?= base_url('landing/blog-view/' . $blogPopular['id']) ?>" style="color:green">Selengkapnya >> </a>
        </div>
      </div>
    <?php endforeach; ?>

  </div>
</div>
<div class="col-sm-12" id="activity">
  <div class="container">
    <h2 class="title-section" style="color:#fff;margin-top:60px">POST TERBAIK</h2>
    <div class="underscore"></div>


    <div class="col-md-12">
      <div id="news-slider" class="owl-carousel">

        <?php foreach ($bestBlogs as $key => $bestBlog) : ?>
          <div data-aos="zoom-in-up">
            <div class="post-slide">
              <div class="post-img">
                <a href="<?= base_url('landing/blog-view/' . $bestBlog['id']) ?>"><img src="<?= asset_url($bestBlog['photo']) ?>" alt=""></a>
              </div>

              <div class="post-content">
                <div class="post-date">
                  <span class="month"><?= format_date($bestBlog['date'], 'm') ?></span>
                  <span class="date"><?= format_date($bestBlog['date'], 'd') ?></span>
                  <span class="month"><?= format_date($bestBlog['date'], 'Y') ?></span>
                </div>

                <h5 class="post-title"><a href="<?= base_url('landing/blog-view/' . $bestBlog['id']) ?>"><?= $bestBlog['title'] ?></a></h5>
                <a href="#" class="isi-suka">
                  <p class="post-description">
                    <?= substr(strip_tags($bestBlog['body']), 0, 110) . "..." ?>
                  </p>
                </a>
              </div>
              <ul class="post-bar">
                <li>Penulis : <a href="<?= base_url('landing/writer/' . $bestBlog['writed_by']) ?>"><?= $bestBlog['writer_name'] ?></a> </li>
              </ul>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>


  </div>
</div>
<div id="all-post-wrapper">
  <div class="container-fluid">
    <div class="col-sm-3">
      <h3 class="title-section title-section-bottom" style="line-height: 1.3em">MAHASISWA <br> TERAKTIF</h3>
      <div class="underscore"></div>
      <?php foreach ($bestWriters as $key => $bestWriter) : ?>
        <div data-aos="zoom-in">
          <a href="<?= base_url('landing/writer/' . $bestWriter['writed_by']) ?>">
            <div class="col-sm-12 post-pengumuman">
              <div class="col-sm-4 col-xs-5 img-pengumuman-wrap">
                <img class="img-responsive img-pengumuman" src="assets/img/trophy.svg" />
              </div>
              <div class="col-sm-8 col-xs-7 main-pengumuman">
                <a href="<?= base_url('landing/writer/' . $bestWriter['writed_by']) ?>" class="aktif">
                  <h5 class="title-pengumuman"><?= $bestWriter['no_student'] ?></h5>
                  <h5 class="title-pengumuman" style="color:#555"><?= $bestWriter['student_name'] ?></h5>
                  <h6 class="title-pengumuman" style="color:#6d9c6f"><?= $bestWriter['count_blog'] ?> Posts</h6>
                </a>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
    <div id="recent-post" class="col-sm-6">
      <h3 class="title-section title-section-bottom">POST TERBARU</h3>
      <div class="underscore"></div>
      <?php foreach ($blogTerbarus as $key => $blogTerbaru) : ?>
        <div data-aos="zoom-in">
          <div class="col-sm-12 post-recent">
            <div class="col-sm-4 col-xs-5 img-recent-wrap">
              <img class="img-responsive img-recent" src="<?= asset_url($blogTerbaru['photo']) ?>" />
            </div>
            <div class="col-sm-8 col-xs-7 main-pengumuman">
              <a href="<?= base_url('landing/blog-view/' . $blogTerbaru['id']) ?>">
                <h4 class="title-recent"><?= $blogTerbaru['title'] ?></h4>
              </a>
              <h6 style="color:#555;font-family: myf">Penulis : <b><?= $blogTerbaru['writer_name'] ?></b><span style="margin-left:10px">
                  Tanggal : <b><?= format_date($blogTerbaru['date'], 'd F Y') ?></b></span></h6>
              <p>
                <?= substr(strip_tags($blogTerbaru['body']), 0, 110) . "..." ?>
              </p>
              <a href="<?= base_url('landing/blog-view/' . $blogTerbaru['id']) ?>" style="color:green">Selengkapnya >> </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="col-sm-3">
      <h3 class="title-section title-section-bottom">AGENDA</h3>
      <div class="underscore"></div>
      <div class="col-sm-12 agenda-wrapper">
        <ul>
          <?php foreach ($agendas as $key => $agenda) : ?>
            <div data-aos="zoom-in">
              <a href="<?= base_url('landing/agenda/' . $agenda['id']) ?>">
                <li class="agenda-post"><i class="glyphicon glyphicon-calendar" style="margin-right:5px"></i><?= $agenda['title'] ?>
                  <p class="date-agenda"><?= format_date($agenda['date'], 'd F Y') ?></p>
                </li>
              </a>
            </div>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>