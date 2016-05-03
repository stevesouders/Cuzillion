<html>
<head>
<title>Cuzillion Help</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<!--
Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

     http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
-->
<link rel="stylesheet" type="text/css" href="cuzillion.css">
<style>
P { margin-top: 0; }
P.normal { margin: 0 8px 8px 8px; }
H1 { margin-left: 8px; margin-right: 8px; }
UL,OL { margin-left: 8px; margin-top: 0; margin-right: 16px; }
.faqlist, .faqlist:visited { text-decoration: none; }
.faqlist:hover { text-decoration: underline; }
#faqlist DT { margin: 0 8px 0 8px; }
#faqs DT { margin: 8px 8px 0 8px; font-size: 1.1em; }
.hoverline, .hoverline:visited { text-decoration: none; }
.hoverline:hover { text-decoration: underline; }
#contents { margin: 8px; }
DT { font-weight: bold; }
#stepbystep DT { margin: 4px 8px 0 8px; }
DD { margin: 0 20px 4px 32px; }
</style>
</head>
<body style='margin: 0px; font-family: "Trebuchet MS", "Bitstream Vera Serif", Utopia, "Times New Roman", times, serif;'>

<div style="background: #333; color: white; padding: 8px;">
  <div style="float:right; margin-top: 2px;">
    <a href="." style="color: white; font-size: 0.9em; text-decoration: none;">back to Cuzillion</a><br>
    <a href="http://stevesouders.com/" style="color: white; font-size: 0.9em; text-decoration: none;">stevesouders.com</a>
  </div>
  <font style="font-size: 2em; font-weight: bold; margin-right: 10px;"><a href="." style="color:white; text-decoration: none;"><img border=0 src="logo-32x32.gif">&nbsp;Cuzillion</a></font><i>'cuz there are a zillion pages to check</i>
</div>

<div id=contents>

<p>
<i>Cuzillion</i> is a tool for quickly constructing web pages to see how components interact. 
Browsers have unexpected behavior in everyday situations (for example, inline scripts block all rendering in the page and nothing can download in parallel with an external script).
Sometimes the behavior differs across browsers (Internet Explorer 8 downloads six items in parallel whereas Firefox 2.0 only downloads two in parallel).
Cuzillion lets you observe these behaviors and share sample pages with others.
</p>

<a name="stepbystep"></a>
<h2>Step-by-Step Instructions</h2>

<dl id=stepbystep>
  <dt> 1. add components
  <dd> 
Click on the type of component that you want 
(<span class="component traybtn extjs" style="display: inline;">external script</span>,
<span class="component traybtn injs" style="display: inline;">inline script</span>,
<span class="component traybtn extcss" style="display: inline;">external stylesheet</span>, etc.)
and it's added to the page avatar.

  <dt> 2. arrange and modify
  <dd> You can drag-and-drop the components to rearrange them in the page avatar. 
All components have default settings that work.
You can change a component's settings by clicking on the edit icon 
(<img src="btn_edit.png">).
The settings available depends on the type of component.
See the <a href="#faq">FAQ</a> for more information.

  <dt> 3. create the page...
  <dd> Click <input type=button value="Create"> to submit your changes.
The server returns the page per your specification. 
Make further changes by clicking on <input type=button value="Edit">.
</dl>


<a name="faq"></a>
<h2 style="margin-top: 40px;">FAQ</h2>

<dl id=faqlist>
  <dt> <a class=faqlist href="#useful">Why is this useful?</a>
  <dt> <a class=faqlist href="#copyright">Can I use the generated code on my site?</a>
  <dt> <a class=faqlist href="#editmode">Why do I have to click on "Edit" to invoke the edit tools? Why not make them there all the time?</a>
  <dt> <a class=faqlist href="#construct">Wow. The different ways of constructing components is confusing. What do "HTML tags", "document.write", "Script DOM element", etc. mean?</a>
  <dt> <a class=faqlist href="#loadtime">How exactly is "page load time" measured?</a>
  <dt> <a class=faqlist href="#resource">What's <code><font style="font-weight: bold; font-size: 1.1em;">resource.cgi</font></code>?</a>
  <dt> <a class=faqlist href="#domains">What do Domain1, Domain2, etc. translate to?</a>
</dl>


<dl id=faqs>
  <a name="useful"></a>
  <dt> Why is this useful? 
	<dd>
It's useful to be able to point to a page that everyone can view that demonstrates a particular browser behavior.
Being able to construct these test pages quickly makes it possible to find more techniques for faster performance.

  <a name="copyright"></a>
  <dt> Can I use the generated code on my site?
	<dd> Yes, this is licensed under the Apache 2.0 License for your use.

  <a name="editmode"></a>
  <dt> Why do I have to click on "Edit" to invoke the edit tools? Why not make them there all the time?
	<dd>
The edit tools require a fair amount of JavaScript and CSS. 
If they were there all the time, they might actually affect the experiment being tested.
My requirement was to render the test page without any external scripts and stylesheets, and without any inline script and style blocks.
(Except for the ones specified as part of the test.)


  <a name="construct"></a>
  <dt> Wow. The different ways of constructing components is confusing. What do "HTML tags", "document.write", "Script DOM element", "iframe", and "XHR eval" mean?
	<dd>
<ul style="font-weight: normal;">
  <li> HTML tags - The component is created in the typical way using HTML. For example, an external script created using HTML tags is:
<pre style="margin: 0 20px 0 20px;">
&lt;script src="resource.cgi?..."&gt;&lt;/script&gt;
</pre>
  <li> document.write - The exact same string used in the HTML tags approach is instead written out via JavaScript as document.write.
  <li> Script DOM Element - Instead of using HTML tags, a DOM element is created via JavaScript. For example, an external script is done as follows. 
This approach is not applicable for inline scripts and inline style blocks.
<pre style="margin: 0 20px 0 20px;">
var script1 = document.createElement('script');
script1.src = "resource.cgi?...";
document.getElementsByTagName('head')[0].appendChild(script1);
</pre>
  <li> iframe - An alternative way to load JavaScript and CSS to avoid blocking is to do it via an iframe. This construction technique returns an iframe that contains either JavaScript or CSS.
  <li> XHR eval - An alternative way to load JavaScript is to <code>eval</code> the response from an XMLHttpRquest. 
</ul>


  <a name="loadtime"></a>
  <dt> How exactly is "page load time" measured?
	<dd>
The page load time is the time from the first packet to the onload event. 
Therefore, it does <i>not</i> include the response time of the HTML document.
I think this is preferred, since what's being tested is the components in the page and not the HTML document itself.
By removing the HTML document response time there's one less variable.


  <a name="resource"></a>
  <dt> What's <code><font style="font-weight: bold; font-size: 1.1em;">resource.cgi</font></code>?
	<dd> tbd


  <a name="domains"></a>
  <dt> What do Domain1, Domain2, etc. translate to?
	<dd>
<ul>
  <li> "Domain1" == <code>1.cuzillion.com</code>
  <li> "Domain2" == <code>2.cuzillion.com</code>
  <li> "Domain3" == <code>3.cuzillion.com</code>
  <li> "Domain4" == <code>4.cuzillion.com</code>
  <li> "Domain5" == <code>5.cuzillion.com</code>
</ul>

</dl>

<div style="margin: 40px 20px 40px 20px;">
<hr>
<div style="float:right;"><a class=hoverline href="http://stevesouders.com" style="font-size: 0.9em; text-decoration: none;">stevesouders.com</a>&nbsp;|&nbsp;<a class=hoverline href="http://stevesouders.com/contact.php" style="font-size: 0.9em; text-decoration: none;">contact Steve</a></div>
</div>

</div> <!-- contents -->

</body>

</html>
