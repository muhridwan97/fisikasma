<div class="form-plaintext">
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">View Devotion</h5>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Name</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['lecturer_name'], 'No name') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">No Lecturer</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['no_lecturer'], '-') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Kegiatan</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['activity'], 'No activity') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Lokasi</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['location'], 'No location') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Sumber Dana</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['source_of_funds'], 'No dana') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tahun</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['year'], 'No year') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Description</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?= if_empty($devotion['description'], 'No description') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Bukti</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <a href="<?= if_empty($devotion['proof_link'], '#') ?>" target="_blank"><?= !empty($devotion['proof_link'])? "Sertifikat": 'No link' ?></a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Status</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?php
                                $statuses = [
                                    DevotionModel::STATUS_ACTIVE => 'success',
                                    DevotionModel::STATUS_INACTIVE => 'secondary',
                                ]
                                ?>
                                <label class="mb-0 small badge badge-<?= $statuses[$devotion['status']] ?>">
                                    <?= $devotion['status'] ?>
                                </label>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card grid-margin">
        <div class="card-body d-flex justify-content-between">
            <button onclick="history.back()" type="button" class="btn btn-light">Back</button>
            <?php if(AuthorizationModel::isAuthorized(PERMISSION_DEVOTION_EDIT)): ?>
                    <a href="<?= site_url('devotion/edit/' . $devotion['id']) ?>" class="btn btn-primary">
                        Edit Devotion
                    </a>
                <?php endif; ?>
        </div>
    </div>
</div>
