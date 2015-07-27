// -- DirtyBeans AppDetection Javascript
function detectDirtyBeans() {
	if (document.cookie.indexOf("DirtyBeans_redirect=false") < 0) {
		if (!navigator.userAgent.match(/Opera/i)) {
			if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i)) || (navigator.userAgent.match(/iPad/i))) {
				if (confirm("DirtyBeans.com has an App! Click OK to learn more about DirtyBeans.DBs.App for iPhone, iPad, iPod.")) {            
					document.cookie = "DirtyBeans_redirect=false"; 
					window.location = "cydia://package/com.modmyi.dirtybeansdbsapp";
				} else {
					setDirtyBeansCookies();
				}
			}
			
		}
	}
}

function setDirtyBeansCookies() {
	var date = new Date();
	var days = 5;
	date.setTime(date.getTime()+(days*24*60*60*1000));
	var expires = "; expires="+ date.toGMTString();
	document.cookie = "DirtyBeans_redirect=false" + expires; 
}

detectDirtyBeans();
