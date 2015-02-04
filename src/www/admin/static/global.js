(function () {
    window.garradin = {
    	url: window.location.href.replace(/\/admin\/.*?$/, ''),
    	admin_url: window.location.href.replace(/\/admin\/.*?$/, '/admin/'),
    	static_url: window.location.href.replace(/\/admin\/.*?$/, '/admin/static/')
    };

	window.$ = function(selector) {
		if (!selector.match(/^[.#]?[a-z0-9_-]+$/i))
		{
			return document.querySelectorAll(selector);
		}
		else if (selector.substr(0, 1) == '.')
		{
			return document.getElementsByClassName(selector.substr(1));
		}
		else if (selector.substr(0, 1) == '#')
		{
			return document.getElementById(selector.substr(1));
		}
		else
		{
			return document.getElementsByTagName(selector);
		}
	};

	garradin.onload = function(callback, dom = true)
    {
    	var eventName = dom ? 'DOMContentLoaded' : 'load';

		if (document.addEventListener)
		{
			document.addEventListener(eventName, callback, false);
		}
		else
		{
			document.attachEvent('on' + eventName, callback);
		}
    };

    window.garradin.toggleElementVisibility = function(selector, visibility)
    {
    	if (!('classList' in document.documentElement))
    		return false;

    	if (selector instanceof Array)
    	{
    		for (var i = 0; i < selector.length; i++)
    		{
    			toggleElementVisibility(selector[i], visibility);
    		}

    		return true;
    	}

        var elements = $(selector);

        for (var i = 0; i < elements.length; i++)
        {
        	if (!visibility)
        		elements[i].classList.add('hidden');
           	else
        		elements[i].classList.remove('hidden');
        }

        return true;
    };

    garradin.script = function (file) {
    	var script = document.createElement('script');
    	script.type = 'text/javascript';
    	script.src = this.static_url + file;
    	return document.head.appendChild(script);
    };

    garradin.style = function (file) {
    	var link = document.createElement('link');
    	link.rel = 'stylesheet';
    	link.type = 'text/css';
    	link.href = this.static_url + file;
    	return document.head.appendChild(link);
    };

    // From KD2fw/js/xhr.js
	garradin.load = function(b,d,f,e){var a=new XMLHttpRequest();if(!a||!b)return false;if(a.overrideMimeType)a.overrideMimeType('text/xml');b+=(b.indexOf('?')+1?'&':'?')+(+(new Date));a.onreadystatechange=function(){if(a.readyState!=4)return;if((s=a.status)==200){if(!d)return true;var c=a.responseText;if(f=='json'){return((j=window.JSON)&&j.parse)?j.parse(c):eval('('+c.replace(/[\n\r]/g,'')+')')}d(c)}else if(e){e(s)}};a.open('GET',b,true);a.send(null)};

	var dateInputFallback = function ()
	{
		var input = document.createElement('input');
		input.setAttribute('type', 'date');
		input.value = ':-)';
		input.style.position = 'absolute';
		input.style.visibility = 'hidden';
		document.body.appendChild(input);

		// If input type changed or value hasn't been sanitized then
		// the input type date element is not supported
		if (input.type === 'text' || input.value === ':-)')
		{
			document.body.removeChild(input);

			if (document.querySelector && !document.querySelector('input[type=date]'))
				return false;

			garradin.script('scripts/datepickr.js');
			garradin.style('scripts/datepickr.css');
		}
		else
		{
			document.body.removeChild(input);
		}
	};

	garradin.onload(dateInputFallback);
})();