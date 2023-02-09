<?php

/* Copyright (C) 2015 fhcomplete.org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
 *
 * Authors: Christopher Gerbrich <christopher.gerbrich@technikum-wien.at>
 */

/*
 * Administrate assignments for LV Templates and Moodle Source Courses
 */

require_once('../lib/Logic.php'); // A lot happens here!

require_once('../../../include/functions.inc.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/lehrveranstaltung.class.php');
require_once('../../../include/sprache.class.php');
require_once('../lib/LogicTemplates.php');

$user = get_uid();

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($user);

$db = new basis_db();
$templates = new LogicTemplates();

if(!$rechte->isBerechtigt('addon/moodle') && !$rechte->isBerechtigt('addon/moodle_quellkurs'))
	die('Sie haben keine Berechtigung für diese Seite');

$curr_lang = getSprache();
$p = new phrasen($curr_lang);

// Messages to be displayed like saving success/error
$msgBox = '';
function add_error($msg) {
	global $msgBox;
	global $p;
	$values = [];
	if (isset($msg[5]) && substr($msg, 0, 4) == 'sql:') {
		$values = [substr($msg, 4)];
		$msg = 'sql';
	}
	$msgBox .= '<span class="error">' . $p->t('moodle/error.' . $msg, $values) . '</span>';
}
function add_success($msg) {
	global $msgBox;
	$p = new phrasen(getSprache());
	$msgBox .= '<span class="ok">' . $p->t('moodle/' . $msg) . '</span>';
}


$sprachen = new sprache();
$sprachen->getAll(true);


$_action = '';
$_edit = false;
if (isset($_GET['template_id'])) {
	$_action = 'template';
	$template = $templates->getTemplate($_GET['template_id']);
	if (!$template) {
		$_action = '';
		add_error('template.wrong');
	} elseif (isset($_POST['mdl_courses'])) {
		if ($error = $templates->updateMoodleQuellkurse($template, $_POST['mdl_courses'], $user)) {
			add_error($error);
		} else {
			add_success('success.template.update');
			$template = $templates->getTemplate($_GET['template_id']);
		}
	}
}
if (isset($_GET['mdl_course_id'])) {
	$_action = 'moodle';
	$mdl_course = $templates->getSourceCourse($_GET['mdl_course_id']);
	if (!$mdl_course) {
		$_action = '';
		add_error('moodle.wrong');
	} elseif (isset($_POST['template']) && isset($_POST['sprache'])) {
		$template = $templates->getTemplate($_POST['template']);
		if ($error = $templates->updateMoodleQuellkurs($mdl_course, $_POST['template'], $_POST['sprache'], isset($_POST['overwrite']), $user)) {
			add_error($error);
		} else {
			add_success('success.template.update');
			$mdl_course = $templates->getSourceCourse($_GET['mdl_course_id']);
		}
	}
}



?>
<!DOCTYPE HTML>
<html>
	<head>
		<meta charset="UTF-8">
		<title><?= $p->t('moodle/quellkurs.title'); ?></title>
		<link rel="stylesheet" href="../../../skin/fhcomplete.css" type="text/css">
		<link rel="stylesheet" href="../../../skin/vilesci.css" type="text/css">
		<link href="../../../skin/jquery-ui-1.9.2.custom.min.css" rel="stylesheet" type="text/css">
		<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
		<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
		<?php require_once '../../../include/meta/jquery-tablesorter.php'; ?>
		<script type="text/javascript">
			$(function(e) {
				$('.action-delete').click(function(e) {
					e.preventDefault();
					$(this).hide().prev().val('').removeAttr('disabled').next().next().text('');
					$('.input-overwrite').data('sprachen', []).trigger('check.overwrite');
				});
				$('.ac-moodle').each(function() {
					let $this = $(this),
						url = 'ac_quellkurs_moodle.php';
					if ($this.data('acFilter'))
						url += '?filter=' + $this.data('acFilter');
					$this.autocomplete({
						minLength: 2,
						source: url,
						close: function(e, ui) {
							$(this).trigger('check.ac-moodle');
						},
						focus: function(e, ui) {
							$(this).removeClass('input_ok input_error').val(ui.item.value).parent().find('.text-mdl_course_name').text(ui.item.label);
							return false;
						},
						select: function (e, ui) {
							$(this).addClass('input_ok').val(ui.item.value).parent().find('.text-mdl_course_name').text(ui.item.label);
							return false;
						}
					}).focus(function(e) {
						$(this).removeClass('input_ok input_error');
					}).blur(function(e) {
						$(this).trigger('check.ac-moodle');
					}).on('check.ac-moodle', function(e) {
						var self = $(this),
							val = self.val();
						if (!val) {
							self.removeClass('input_ok input_error').parent().find('.text-mdl_course_name').text('');
							self.siblings('.action-send').attr('disabled', true);
						} else {
							$.ajax({
								url: url,
								dataType: 'json',
								data: {
									'term': val
								},
								success: function(data) {
									if (self.val() == val) {
										var label = '',
											state = '';

										if (data[val]) {
											state = 'input_ok';
											label = data[val].label;
										} else if (val) {
											state = 'input_error';
										}
										self.removeClass('input_ok input_error').addClass(state).parent().find('.text-mdl_course_name').text(label);
									}
									if (self.is('.input_ok'))
										self.siblings('.action-send').removeAttr('disabled');
									else
										self.siblings('.action-send').attr('disabled', true);
								}
							});
						}
					}).trigger('check.ac-moodle');
				});

				$('.input-overwrite').on('check.overwrite', function(e) {
					var self = $(this),
						sprache = $('[name="sprache"]').val();
					console.log(sprache);
					if (self.data('sprachen')[sprache]) {
						self.show().find('[name="overwrite"]').removeProp('checked');
					} else {
						self.hide().find('[name="overwrite"]').removeProp('checked');
					}
				});

				$('[name="sprache"]').change(function(e) {
					$('.input-overwrite').trigger('check.overwrite');
				});
				
				$('.ac-template').each(function() {
					let $this = $(this),
						url = 'ac_quellkurs_template.php';
					if ($this.data('acFilter'))
						url += '?filter=' + $this.data('acFilter');
					$this.autocomplete({
						minLength: 2,
						source: url,
						close: function(e, ui) {
							$(this).trigger('check.ac-template');
						},
						focus: function(e, ui) {
							$(this).removeClass('input_ok input_error').val(ui.item.value).parent().find('.text-template_name').text(ui.item.label);
							return false;
						},
						select: function (e, ui) {
							$(this).addClass('input_ok').val(ui.item.value).parent().find('.text-template_name').text(ui.item.label);
							$('.input-overwrite').data('sprachen', ui.item.sprachen).trigger('check.overwrite');
							return false;
						}
					}).focus(function(e) {
						$(this).parent().removeClass('input_ok input_error');
					}).blur(function(e) {
						$(this).trigger('check.ac-template');
					}).on('check.ac-template', function(e) {
						var self = $(this),
							val = self.val();
						if (!val) {
							self.removeClass('input_ok input_error').parent().find('.text-template_name').text('');
							self.siblings('.action-send').attr('disabled', true);
							$('.input-overwrite').data('sprachen', []).trigger('check.overwrite');
						} else {
							$.ajax({
								url: url,
								dataType: 'json',
								data: {
									'term': val
								},
								success: function(data) {
									if (self.val() == val) {
										var label = '',
											state = '',
											sprachen = [];

										if (data[val]) {
											state = 'input_ok';
											label = data[val].label;
											sprachen = data[val].sprachen;
										} else if (val) {
											state = 'input_error';
										}
										self.removeClass('input_ok input_error').addClass(state).parent().find('.text-template_name').text(label);
										$('.input-overwrite').data('sprachen', sprachen).trigger('check.overwrite');
									}
									if (self.is('.input_ok')) {
										self.siblings('.action-send').removeAttr('disabled');
									} else {
										self.siblings('.action-send').attr('disabled', true);
									}
								}
							});
						}
					}).trigger('check.ac-template');
				});
			});
		</script>
		<style type="text/css">
			.action-delete {
				border: solid 1px #999;
				border-left: 0;
				background: none;
			}
			thead th {
				color: gray;
			}
			td, th {
				padding: .1em .2em;
			}
		</style>
	</head>

<?php
echo '
<body>
	<h1>' . $p->t('moodle/quellkurs.title') . '</h1>
	<span id="msgBox">'. $msgBox. '</span>';

if ($_action == 'moodle') {
	$value = '';
	$label = '';
	if ($mdl_course->template_id) {
		$value = ' disabled value="' . $mdl_course->template_id . '"><button class="action-delete"><img src="../skin/images/tree-diagramm-delete.png" title="' . $p->t('moodle/quellkurs.form.btn.delete') . '" height="12"></button';
		$label = $templates->getTemplate($mdl_course->template_id);
		$label = $label ? $label->bezeichnung : '';
	}
	if (isset($_POST['template'])) {
		$value = ' value="' . $_POST['template'] . '"';
		$label = '';
	}
	echo '
	<h2>' . ($mdl_course->template_id ? $p->t('moodle/quellkurs.title.assignment.change') : $p->t('moodle/quellkurs.title.assignment.new')) . ' - <a href="?" class="ui-button ui-button-secondary">' . $p->t('moodle/form.btn.back') . '</a></h2>
	<form method="POST">
		<fieldset>
			<legend>' . $mdl_course->fullname . ' (' . $mdl_course->id . ')</legend>
			<table>
				<tbody>
					<tr>
						<th align="left">' . $p->t('moodle/quellkurs.form.label.template') . '</th>
						<td>
							<input type="text" class="ac-template" data-ac-filter="' . $_GET['mdl_course_id'] . '" name="template"' . $value . '>
							<span class="text-template_name">' . $label . '</span>
						</td>
					</tr>
					<tr>
						<th align="left">' . $p->t('moodle/quellkurs.form.label.language') . '</th>
						<td>
							<select name="sprache">';
	foreach ($sprachen->result as $sprache) {
		$selected = ($mdl_course->template_sprache == $sprache->sprache) ? ' selected' : '';
		if (isset($_POST['sprache']))
			$selected = ($_POST['sprache'] == $sprache->sprache) ? ' selected' : '';
		echo '
								<option value="' . $sprache->sprache . '"' . $selected . '>' . $sprache->bezeichnung_arr[$curr_lang] . '</option>';
	}
	echo '
							</select>
						</td>
					</tr>
					<tr class="input-overwrite">
						<th align="left">' . $p->t('moodle/quellkurs.form.label.overwrite') . '</th>
						<td>
							<input type="checkbox" name="overwrite" value="1">
						</td>
					</tr>
				</tbody>
				<tfoot>
					<tr>
						<td>&nbsp;</td>
						<td><button type="submit">' . $p->t('moodle/form.btn.save') . '</button></td>
					</tr>
				</tfoot>
			</table>
		</fieldset>
	</form>';
} elseif ($_action == 'template') {
	echo '
	<h2>' . (count($template->mdl_courses) ? $p->t('moodle/quellkurs.title.assignment.change') : $p->t('moodle/quellkurs.title.assignment.new')) . ' - <a href="?" class="ui-button ui-button-secondary">' . $p->t('moodle/form.btn.back') . '</a></h2>
	<form method="POST">
		<fieldset>
			<legend>' . $template->bezeichnung . ' (' . $template->lehrveranstaltung_id . ')</legend>
			<table>
				<thead>
					<tr>
						<th align="left">' . $p->t('moodle/quellkurs.form.label.language') . '</th>
						<th align="left">' . $p->t('moodle/quellkurs.form.label.moodle_course') . '</th>
					</tr>
				</thead>
				<tbody>';
	$trs = [];
	foreach ($sprachen->result as $sprache) {
		$isDefault = ($sprache->sprache == $template->sprache);
		$tc = $isDefault ? 'th' : 'td';
		$value = '';
		$label = '';
		if (isset($template->mdl_courses[$sprache->sprache])) {
			$value = ' disabled value="' . $template->mdl_courses[$sprache->sprache] . '"><button class="action-delete"><img src="../skin/images/tree-diagramm-delete.png" title="' . $p->t('moodle/quellkurs.form.btn.delete') . '" height="12"></button';
			$label = $templates->getSourceCourse($template->mdl_courses[$sprache->sprache]);
			$label = $label ? $label->fullname : '';
		}
		$tr = '
					<tr>
						<' . $tc . ' align="left">' . $sprache->bezeichnung_arr[$curr_lang] . '</' . $tc . '>
						<td>
							<input type="text" class="ac-moodle" data-ac-filter="' . $template->studiengang_kz . '" name="mdl_courses[' . $sprache->sprache . ']"' . $value . '>
							<span class="text-mdl_course_name">' . $label . '</span>
						</td>
					</tr>';
		if ($isDefault) {
			array_unshift($trs, $tr);
		} else {
			array_push($trs, $tr);
		}
	}

	echo implode('', $trs) . '
				</tbody>
				<tfoot>
					<tr>
						<td>&nbsp;</td>
						<td><button type="submit">' . $p->t('moodle/form.btn.save') . '</button></td>
					</tr>
				</tfoot>
			</table>
		</fieldset>
	</form>';
} else {
	echo '
	<h2>' . $p->t('moodle/quellkurs.title.assignment') . '</h2>
	<fieldset>
		<legend>' . $p->t('moodle/quellkurs.title.assignment.new') . '</legend>
		<table>
			<tr>
				<td><b>' . $p->t('moodle/quellkurs.form.label.moodle_course') . '</b></td>
				<td>
					<form method="GET" action="quellkurs_verwaltung.php">
						<input type="text" class="ac-moodle" name="mdl_course_id" value="">
						<button type="submit" class="action-send">&gt;</button>
						<span class="text-mdl_course_name"></span>
					</form>
				</td>
			</tr>
			<tr>
				<td colspan="2">-oder-</td>
			</tr>
			<tr>
				<td><b>' . $p->t('moodle/quellkurs.form.label.template') . '</b></td>
				<td>
					<form method="GET" action="quellkurs_verwaltung.php">
						<input type="text" class="ac-template" name="template_id" value="">
						<button type="submit" class="action-send">&gt;</button>
						<span class="text-template_name"></span>
					</form>
				</td>
			</tr>
		</table>
	</fieldset>
	&nbsp;
	<fieldset>
		<legend>' . $p->t('moodle/quellkurs.title.unassigned') . '</legend>';

	if (isset($_GET['missing_templates'])) {
		echo '
		<table>
			<tr class="liste"><th>' . $p->t('moodle/model.lv.id') . '</th><th>' . $p->t('moodle/model.lv.bezeichnung') . '</th></tr>';

		$db = new basis_db();
		$qry = "SELECT lv.lehrveranstaltung_id, lv.bezeichnung 
			FROM lehre.tbl_lehrveranstaltung AS lv 
			LEFT JOIN addon.tbl_moodle_quellkurs AS m 
				ON lv.lehrveranstaltung_id=m.lehrveranstaltung_id
			WHERE lehrtyp_kurzbz='tpl' AND m.moodle_quellkurs_id IS NULL";
			
		$i = 0;
		if ($result = $db->db_query($qry)) {
			while ($lv = $db->db_fetch_object($result)) {
				echo '
			<tr class="liste' . ($i++%2) . '"><td><a href="?template_id=' . $lv->lehrveranstaltung_id . '">' . $lv->lehrveranstaltung_id . '</a></td><td><a href="?template_id=' . $lv->lehrveranstaltung_id . '">' . $lv->bezeichnung . '</a></td></tr>';
			}
		}

		echo '
		</table>';
	} else {
		echo '
		<form method="GET" action="quellkurs_verwaltung.php">
			<button type="submit" name="missing_templates">' . $p->t('moodle/form.btn.show') . '</button>
		</form>';
	}

	echo '
	</fieldset>';
}

echo '
</body>
</html>';
