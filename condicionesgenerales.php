<?php
$page_title = 'Términos y Condiciones | Relax Tours';
$page_description = 'Lee nuestros términos y condiciones generales para la contratación de servicios turísticos.';
$active_page = 'condiciones';
require_once 'includes/header.php';
?>

<main class="main-content">
    <div class="container" style="padding-top: 40px; padding-bottom: 40px;">
        <h1 class="section-title text-center" style="margin-bottom: 30px;">Términos y Condiciones</h1>
        
        <div id="adobe-dc-view" style="width: 100%; height: 165vh; border: 1px solid #eee;"></div>
    </div>
</main>

<script src="https://acrobatservices.adobe.com/view-sdk/viewer.js"></script>
<script type="text/javascript">
    document.addEventListener("adobe_dc_view_sdk.ready", function(){ 
        var adobeDCView = new AdobeDC.View({
            clientId: "d3fa0060f8d74d16b0a2bb7881195c45", 
            divId: "adobe-dc-view"
        });
        adobeDCView.previewFile({
            content: { 
                location: { url: "<?php echo $base_url; ?>files/CondicionesGenerales.pdf" } 
            },
            metaData: { fileName: "Condiciones Generales - Relax Tours.pdf" }
        }, {
            embedMode: "SIZED_CONTAINER", 
            defaultViewMode: "CONTINUOUS_PAGES", 
            showDownloadPDF: true,
            showPrintPDF: true,
            showAnnotationTools: false,
            showLeftHandPanel: false,
            showPageControls: true,
            dockPageControls: true,
            
        });
</script>

<?php require_once 'includes/footer.php'; ?>
