<!-- Convead Widget -->
<script>
window.ConveadSettings = {
<?php if ($visitor_uid): ?>
    visitor_uid: '<?php echo $visitor_uid; ?>',
    visitor_info: <?php echo json_encode($visitor_info); ?>,
<?php endif; ?>
    app_key: "<?php echo $app_key; ?>"

    /* For more information on widget configuration please see:
       http://convead.ru/help/kak-nastroit-sobytiya-vizitov-prosmotrov-tovarov-napolneniya-korzin-i-pokupok-dlya-vashego-sayta
    */
};

(function(w,d,c){w[c]=w[c]||function(){(w[c].q=w[c].q||[]).push(arguments)};var ts = (+new Date()/86400000|0)*86400;var s = d.createElement('script');s.type = 'text/javascript';s.async = true;s.charset = 'utf-8';s.src = 'https://tracker.convead.io/widgets/'+ts+'/widget-<?php echo $app_key; ?>.js';var x = d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s, x);})(window,document,'convead');
<?php if ($view_product): ?>
convead('event', 'view_product', <?php echo json_encode($view_product); ?>);
<?php endif; ?>
</script>
<!-- /Convead Widget -->
