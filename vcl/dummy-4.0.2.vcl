vcl 4.0;

backend default {
    .host = "{{backend_host}}";
    .port = "{{backend_port}}";
}

sub vcl_recv {

    if (req.method == "PURGE") {
        return (purge);
    }

    if (req.method == "PURGE") {
        return (purge);
    }

    if (req.method == "BAN") {
        ban("obj.http.x-url ~ " + req.http.x-ban-url + " && obj.http.x-host ~ " + req.http.x-ban-host);
        return (synth(200, "Ban added"));
    }

    return (hash);
}

sub vcl_backend_response {
    set beresp.http.x-url = bereq.url;
    set beresp.http.x-host = bereq.http.host;

    set beresp.do_gzip = true;

    if (beresp.status != 200 && beresp.status != 404) {
        set beresp.ttl = 15s;
        set beresp.uncacheable = true;
        return (deliver);
    }

    if (beresp.http.X-VarnishBakery-Cache == "0") {
        set beresp.ttl = 0s;
        set beresp.uncacheable = true;
        set beresp.http.Cache-Control = "no-store, no-cache, must-revalidate";
        return (deliver);
    }
}

sub vcl_deliver {
    if ({{debug_mode}} && obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }

    if(!{{debug_mode}}) {
        unset resp.http.x-url;
        unset resp.http.x-host;
        unset resp.http.X-Powered-By;
        unset resp.http.X-Varnish;
        unset resp.http.X-Cache;
        unset resp.http.X-Varnishbakery-Cache;
    }
}