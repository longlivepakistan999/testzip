<div class="container">
	<div class="row">
		<div class="col-12">
			<h1>Results</h1>
			<script type="text/javascript">
			    const queryString = window.location.search;
			    const urlParams = new URLSearchParams(queryString);
			    
			    if(urlParams.has('site')){
			        fetch('https://search.emory.edu/cse.json')
                        .then(response => response.json())
                        .then(function(data){
                            const siteName = urlParams.get('site');
                            data.forEach(function(cseEl){
                                if(cseEl.collection_name == siteName){
                                    if (!(document.getElementById('emory-cse'))) {
                                		window.gt = (function(d, s, id) {
                                			var js, fjs = d.getElementsByTagName(s)[0],
                                				t = window.gt || {};
                                			if (d.getElementById(id)) return t;
                                			js = d.createElement(s);
                                			js.id = id;
                                			js.src = "https://cse.google.com/cse.js?cx="+cseEl.cse_id;
                                			fjs.insertAdjacentElement('beforeend', js);
                                	
                                			t._e = [];
                                			t.ready = function(f) {
                                				t._e.push(f);
                                			};
                                	
                                			return t;
                                		}(document, "script", "emory-cse"));
                                	}
                                    
                                }
                            });  
                        });
			    } else {
			        if (!(document.getElementById('emory-cse'))) {
                		window.gt = (function(d, s, id) {
                			var js, fjs = d.getElementsByTagName(s)[0],
                				t = window.gt || {};
                			if (d.getElementById(id)) return t;
                			js = d.createElement(s);
                			js.id = id;
                			js.src = "https://cse.google.com/cse.js?cx=017001689490773891133:vootn8ijbso";
                			fjs.insertAdjacentElement('beforeend', js);
                	
                			t._e = [];
                			t.ready = function(f) {
                				t._e.push(f);
                			};
                	
                			return t;
                		}(document, "script", "emory-cse"));
                	}
			    }
			</script>
            <div class="gcse-search"></div>
		</div>
	</div>
	<!-- ./row -->
	<div class="row">
		<div class="col-12">
			<a class="btn-primary btn btn-secondary mb-4 mt-4" href="http://directory.service.emory.edu/" target="_self">
				<span class="formatted-header">People&#160;Directory</span>&#160;<i class="fas fa-external-link-alt"></i>
			</a>
		</div>
	</div>
</div>
<!-- ./container -->