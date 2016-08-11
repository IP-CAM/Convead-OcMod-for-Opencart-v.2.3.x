<?php echo $header; ?><?php echo $column_left; ?>

<div id="content">

	<div class="page-header">
		<div class="container-fluid">
			<div class="pull-right">
				<button type="submit" form="form-convead" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
			<a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
			<h1><?php echo $heading_title; ?></h1>
			<ul class="breadcrumb">
				<?php foreach ($breadcrumbs as $breadcrumb): ?>
					<li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>

	<div class="container-fluid">
		<?php if ($error_warning): ?>
			<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
				<button type="button" class="close" data-dismiss="alert">&times;</button>
			</div>
		<?php endif; ?>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
			</div>
			<div class="panel-body">
				<form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-convead" class="form-horizontal">
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
						<div class="col-sm-10">
							<select name="convead_status" id="input-status" class="form-control">
								<?php if ($convead_status): ?>
									<option value="1" selected="selected"><?php echo $text_enabled; ?></option>
									<option value="0"><?php echo $text_disabled; ?></option>
									<?php else: ?>
									<option value="1"><?php echo $text_enabled; ?></option>
									<option value="0" selected="selected"><?php echo $text_disabled; ?></option>
								<?php endif; ?>
							</select>
						</div>			
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="input-apikey"><?php echo $entry_apikey; ?></label>
						<div class="col-sm-10">
							<input name="convead_apikey" id="input-apikey" class="form-control" type="text" value="<?php echo $convead_apikey;?>">                
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>

</div>

<?php echo $footer; ?>