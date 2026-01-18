<div class="container">
    <div class="row">
        <div class="col-12">
            <form class="search-form" id="universitySearch" method="GET" action="/search">
				<div class="input-group input-group-lg">
					<input type="text" class="form-control" placeholder="Search..." value="<?php echo $xml->Q; ?> " aria-label="Search Emory University" name="q"/>
					<input name="filter" type="hidden" value="0" />
					<input name="proxystylesheet" type="hidden" value="homepage" />
					<div class="input-group-append">
						<input type="submit" value="Search" class="btn btn-outline-primary" />
					</div>
					<!-- /.input-group-append -->
				</div>
				<!-- /.input-group -->
			</form>
			<p class="lead mt-5">
			    Results <?php echo $xml->RES['SN']; ?> - <?php echo $xml->RES['EN']; ?> of about <?php echo $xml->RES->M; ?> for <?php echo $xml->Q; ?>. 
			</p>
			
			<ul class="list-unstyled">
			    <?php foreach ($xml->xpath('/GSP/RES/R') as $searchResult) { ?>
			    <li class="mt-3">
			        <h2 class="h5"><?php echo $searchResult->T; ?></h2>
			        <p><?php echo $searchResult->S; ?><br /><a href="<?php echo $searchResult->U; ?>"><?php echo $searchResult->U; ?></a></p>
			    </li>
			    <?php } ?>
			</ul>
			<a class="btn btn-outline-primary" href="<?php echo $xml->RES->NB->NU ?>">Next</a>
        </div>
    </div>
</div>