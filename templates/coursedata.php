<?php
global $LACONN;

if (!empty($sections)) :?>
    <ul class="sections course-expand lmsace-connect" id="lmsace-connect-summary-accord">
        <?php $i = 1;
        foreach ($sections as $section) : ?>
        <li class="section-item">
            <h2 class="section-title"> <a href="#" > <?php echo ($section->name != "") ? wp_kses( $section->name, $LACONN->allowed_tags() ) : (($i==1) ? __('General', 'lmsace-connect') : ''); $i++; ?></a> </h2>
            <?php if (!empty($section->cmlist)) : ?>
            <ul class="modules-list">
                <?php foreach ($section->cmlist as $cm) : ?>
                    <li class="module-item">
                        <div class="img-block-laconn">
                            <img class="modicon" src="<?php echo $cm->modicon; ?>" alt="<?php echo $cm->modname; ?>"/>
                        </div>
                        <div class="mod-content">
                            <label><?php echo $cm->modname; ?></label>
                            <h4 class="module-title"> <?php echo $cm->name; ?> </h4>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

