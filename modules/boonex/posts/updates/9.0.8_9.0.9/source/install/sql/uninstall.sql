
-- TABLES
DROP TABLE IF EXISTS `bx_posts_posts`, `bx_posts_files`, `bx_posts_photos`, `bx_posts_photos_resized`, `bx_posts_cmts`, `bx_posts_votes`, `bx_posts_votes_track`, `bx_posts_views_track`, `bx_posts_meta_keywords`, `bx_posts_meta_locations`, `bx_posts_meta_mentions`, `bx_posts_reports`, `bx_posts_reports_track`, `bx_posts_favorites_track`, `bx_posts_scores`, `bx_posts_scores_track`;

-- STORAGES & TRANSCODERS
DELETE FROM `sys_objects_storage` WHERE `object` IN ('bx_posts_files', 'bx_posts_photos', 'bx_posts_photos_resized');
DELETE FROM `sys_storage_tokens` WHERE `object` IN ('bx_posts_files', 'bx_posts_photos', 'bx_posts_photos_resized');

DELETE FROM `sys_objects_transcoder` WHERE `object` IN('bx_posts_preview', 'bx_posts_gallery', 'bx_posts_cover', 'bx_posts_preview_photos', 'bx_posts_gallery_photos');
DELETE FROM `sys_transcoder_filters` WHERE `transcoder_object` IN('bx_posts_preview', 'bx_posts_gallery', 'bx_posts_cover', 'bx_posts_preview_photos', 'bx_posts_gallery_photos');
DELETE FROM `sys_transcoder_images_files` WHERE `transcoder_object` IN('bx_posts_preview', 'bx_posts_gallery', 'bx_posts_cover', 'bx_posts_preview_photos', 'bx_posts_gallery_photos');

-- FORMS
DELETE FROM `sys_objects_form` WHERE `module` = 'bx_posts';
DELETE FROM `sys_form_displays` WHERE `module` = 'bx_posts';
DELETE FROM `sys_form_inputs` WHERE `module` = 'bx_posts';
DELETE FROM `sys_form_display_inputs` WHERE `display_name` IN ('bx_posts_entry_add', 'bx_posts_entry_edit', 'bx_posts_entry_view', 'bx_posts_entry_delete');

-- PRE-VALUES
DELETE FROM `sys_form_pre_lists` WHERE `module` = 'bx_posts';

DELETE FROM `sys_form_pre_values` WHERE `Key` IN('bx_posts_cats');

-- COMMENTS
DELETE FROM `sys_objects_cmts` WHERE `Name` = 'bx_posts';

-- VOTES
DELETE FROM `sys_objects_vote` WHERE `Name` = 'bx_posts';

-- SCORES
DELETE FROM `sys_objects_score` WHERE `name` = 'bx_posts';

-- REPORTS
DELETE FROM `sys_objects_report` WHERE `name` = 'bx_posts';

-- VIEWS
DELETE FROM `sys_objects_view` WHERE `name` = 'bx_posts';

-- FAFORITES
DELETE FROM `sys_objects_favorite` WHERE `name` = 'bx_posts';

-- FEATURED
DELETE FROM `sys_objects_feature` WHERE `name` = 'bx_posts';

-- CONTENT INFO
DELETE FROM `sys_objects_content_info` WHERE `name` IN ('bx_posts', 'bx_posts_cmts');

DELETE FROM `sys_content_info_grids` WHERE `object` IN ('bx_posts');

-- SEARCH EXTENDED
DELETE FROM `sys_objects_search_extended` WHERE `module` = 'bx_posts';

-- STUDIO: page & widget
DELETE FROM `tp`, `tw`, `tpw`
USING `sys_std_pages` AS `tp`, `sys_std_widgets` AS `tw`, `sys_std_pages_widgets` AS `tpw`
WHERE `tp`.`id` = `tw`.`page_id` AND `tw`.`id` = `tpw`.`widget_id` AND `tp`.`name` = 'bx_posts';

