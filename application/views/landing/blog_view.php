<div class="col-md-9 list-into-single">
	<div>
		<p class="list-page-single"><a href="<?= base_url() ?>">Home</a></p>>><p class="list-page-single"><a href="<?= base_url('/landing/blog/').$blog['category'] ?>"><?=$blog['category']?></a></p>
	</div>
</div>
<div class="col-md-9 single-post-posts" style="padding-bottom: 20px">

	<div id="title-post">
		<h2><?= $blog['title'] ?></h2>
	</div>
	<div class="detail-post">
		<p class="date-post">
			<span class="glyphicon glyphicon-dashboard" style="margin-right:5px;color:rgb(28, 71, 128)"></span><b>Tanggal :</b>
			<span class="text-date-post"><?= format_date($blog['date'], 'd F Y') ?></span>
		</p>
		<p class="created-post" style="margin-right:10px">
			<span class="glyphicon glyphicon-user" style="margin-right:5px;color:rgb(28, 71, 128)"></span><b>Ditulis oleh : </b>
			<span class="text-created-post"><?= $blog['writer_name'] ?></span>
		</p>
		<p class="created-post">
			<span class="glyphicon glyphicon-star" style="margin-right:5px;color:rgb(28, 71, 128)"></span><b>Dilihat oleh : </b>
			<span class="text-created-post"><?= $blog['count_view'] ?></span>
		</p>
	</div>
	<?php if (!empty($blog['attachment'])) : ?>
		<a href="<?= base_url() . 'uploads/' . $blog['attachment'] ?>">
			<button class="btn btn-primary">Lihat Lampiran</button>
		</a>
	<?php endif; ?>
	<div id="img-post-wrap">
		<img class="img-responsive img-post" src="<?= asset_url($blog['photo']) ?>" />
	</div>
	<p id="isi-post">
	<div style="text-align:justify">
		<?= $blog['body'] ?>
	</div>


	<br>
	<br>
	<br>

	</p>
	<div id="post-bottom-wrap">
		<div data-aos="zoom-up">
			<div id="terkait-post" class="col-sm-6">
				<h3>POST TERKAIT</h3>
				<div class="underscore" style="margin-left:0px;margin-left:0px;margin-bottom:15px;"></div>
				<ul id="terkait-post-list">
					<li><a href="kuliah-via-daring-hanya-formalitas-2020-06-2807-45-54.html">Kuliah via Daring, Hanya Formalitas?</a></li>
					<li><a href="gangguan-mental-pada-masa-pandemi2020-10-0601-35-38.html">Gangguan Mental Pada Masa Pandemi</a></li>
					<li><a href="omnibus-law-bentuk-nyata-fungsi-hukum-tidak-lagi-sebagai-pelaksana-kehendak-rakyat2020-10-0609-48-16.html">OMNIBUS LAW : BENTUK NYATA FUNGSI HUKUM TIDAK LAGI SEBAGAI PELAKSANA KEHENDAK RAKYAT</a></li>
					<li><a href="renjana-tak-bertepi2020-10-2006-46-50.html">Renjana Tak Bertepi</a></li>
				</ul>
			</div>
		</div>
		<div data-aos="zoom-up">
			<div id="terbaru-post" class="col-sm-6">
				<h3>POST TEBARU</h3>
				<div class="underscore" style="margin-left:0px;margin-left:0px;margin-bottom:15px;"></div>
				<ul id="terbaru-post-list">
					<li><a href="membangun-generasi-melalui-pendidikan-sebagai-investasi-masa-depan-yang-lebih-cerah2022-04-2702-16-42.html">Membangun Generasi Melalui Pendidikan Sebagai Investasi Masa Depan Yang Lebih Cerah</a></li>
					<li><a href="bukan-puasa-yang-bikin-jerawat-datang-ayo-intip-penyebabnya-2022-04-2503-26-05.html">Bukan Puasa yang Bikin Jerawat Datang! Ayo Intip Penyebabnya!</a></li>
					<li><a href="sosok-kartini-masa-kini-bagi-kemajuan-pendidikan-dan-perempuan2022-04-2423-36-28.html">Sosok Kartini Masa Kini Bagi Kemajuan Pendidikan dan Perempuan</a></li>
					<li><a href="menuju-indonesia-maju-dengan-pendidikan-anak-usia-dini2022-04-2103-03-13.html">Menuju Indonesia maju dengan pendidikan anak usia dini</a></li>
					<li><a href="rangkulan-untuk-para-penyintas-kekerasan-seksual2022-04-1404-21-39.html">Rangkulan untuk Para Penyintas Kekerasan Seksual</a></li>
				</ul>
			</div>
		</div>
	</div>

</div>