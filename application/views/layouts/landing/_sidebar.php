<?php
$segment1 = $this->uri->segment(1);
$segment2 = $this->uri->segment(2);
?>

<div id="wrap-sidebar-single" class="col-md-3">
    <div id="populer-wrap" class="col-md-12 col-sm-6">
        <div class="title-sidebar">
            <h4>
                <span class="glyphicon glyphicon-file"></span>
                <b>POST</b> POPULER
            </h4>
            <div class="underscore" style="margin-left:0px;margin-left:0px;margin-bottom:15px;"></div>
        </div>

        <div id="post-populer-sidebar">
            <ul id="post-populer-sidebar-list">
                <?php foreach ($blogPopulars as $key => $blogPopular) : ?>
                    <li><a href="<?= base_url('landing/blog-view/' . $blogPopular['id']) ?>"><span class="glyphicon glyphicon-file" style="margin-right:5px"></span><?= $blogPopular['title'] ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div id="agenda-wrap" class="col-md-12 col-sm-6">
        <div class="title-sidebar" style="margin-top:15px;">
            <h4>
                <span class="glyphicon glyphicon-calendar"></span>
                <b>AGENDA</b> TERKINI

            </h4>
            <div class="underscore" style="margin-left:0px;margin-left:0px;margin-bottom:15px;"></div>
        </div>
        <div id="agenda-list-sidebar">
            <div class="col-sm-12 agenda-wrapper">
                <ul style="padding-left:5px">                
                <?php foreach ($agendas as $key => $agenda) : ?>
                    <li class="agenda-post-sidebar">
                        <a href="<?= base_url('landing/blog-view/' . $agenda['id']) ?>">
                            <i class="glyphicon glyphicon-calendar" style="margin-right:5px"></i>
                            <?= $agenda['title'] ?><p class="date-agenda-sidebar"><?= format_date($agenda['date'],'d F Y') ?></p>
                        </a>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>