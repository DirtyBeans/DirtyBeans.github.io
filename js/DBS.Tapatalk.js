// -- DirtyBeans AppDetection Javascript
function detectTapatalk() {
	if (document.cookie.indexOf("tapatalk_redirect=false") < 0) {
		if (!navigator.userAgent.match(/Opera/i)) {
			if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i)) || (navigator.userAgent.match(/iPad/i))) {
				if (confirm("DirtyBeans.com has an iPhone app! Click OK to learn more about DBs for iPhone, iPad, iPod.")) {            
					document.cookie = "tapatalk_redirect=false"; 
					window.location = "http://www.DirtyBeans.com/repo/";
				} else {
					setTapatalkCookies();
				}
			}
			
		}
	}
}

function setTapatalkCookies() {
	var date = new Date();
	var days = 5;
	date.setTime(date.getTime()+(days*24*60*60*1000));
	var expires = "; expires="+ date.toGMTString();
	document.cookie = "tapatalk_redirect=false" + expires; 
}

detectTapatalk();
