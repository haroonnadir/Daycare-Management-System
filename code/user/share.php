<?php
// share.php
$pageTitle = "ABC Daycare Services";
$pageDescription = "Quality childcare services with experienced staff and a nurturing environment";
$imageUrl = "https://yourdaycare.com/images/logo.jpg"; // Your daycare logo or photo

// Get the referring page if specified
$pageUrl = isset($_GET['url']) ? $_GET['url'] : 'https://yourdaycare.com';
?>

<!DOCTYPE html>
<html>
<head>
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($imageUrl) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>">
    <meta name="twitter:card" content="summary_large_image">
    
    <title>Share Our Daycare Services</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
        .share-container { max-width: 600px; margin: 0 auto; }
        .share-message { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="share-container">
        <h1>Share ABC Daycare</h1>
        <div class="share-message">
            <p>We're so glad you love our services! Help us grow by sharing with your friends and family.</p>
            <p><strong>Customize your message:</strong></p>
            <textarea id="customMessage" rows="3" style="width: 100%;">Check out ABC Daycare - amazing childcare services! <?= htmlspecialchars($pageUrl) ?></textarea>
        </div>
        
        <!-- Social buttons from previous example -->
        <div class="share-buttons">
            <!-- Include the same social buttons as in the first example -->
        </div>
        
        <p>or copy this link: <input type="text" id="shareUrl" value="<?= htmlspecialchars($pageUrl) ?>" style="width: 100%; padding: 5px;" readonly></p>
    </div>

    <script>
        // Update sharing with custom message
        document.addEventListener('DOMContentLoaded', function() {
            const customMessage = document.getElementById('customMessage');
            const shareUrlInput = document.getElementById('shareUrl');
            
            // Update all share links when message changes
            customMessage.addEventListener('input', function() {
                const message = encodeURIComponent(customMessage.value);
                const pageUrl = encodeURIComponent(shareUrlInput.value);
                
                // Update all share buttons with the new message
                document.querySelectorAll('.share-btn').forEach(btn => {
                    const platform = btn.getAttribute('data-platform');
                    let newHref = '#';
                    
                    switch(platform) {
                        case 'facebook':
                            newHref = `https://www.facebook.com/sharer/sharer.php?u=${pageUrl}&quote=${message}`;
                            break;
                        case 'twitter':
                            newHref = `https://twitter.com/intent/tweet?text=${message}`;
                            break;
                        case 'whatsapp':
                            newHref = `https://wa.me/?text=${message}`;
                            break;
                        case 'email':
                            newHref = `mailto:?body=${message}`;
                            break;
                    }
                    
                    btn.setAttribute('href', newHref);
                });
            });
            
            // Copy link functionality
            shareUrlInput.addEventListener('click', function() {
                this.select();
                document.execCommand('copy');
                alert('Link copied to clipboard!');
            });
        });
        /**
 * Generates social sharing buttons with dynamic content
 * 
 * @param string $url The URL to share (defaults to current page)
 * @param string $title The title to share (defaults to page title)
 * @param string $image URL of image to share (defaults to site logo)
 * @param string $description Description text for sharing
 * @return string HTML for social sharing buttons
 */
function generateSocialSharing($url = '', $title = '', $image = '', $description = '') {
    // Set defaults
    $url = $url ?: (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $title = $title ?: get_bloginfo('name'); // Or your custom title
    $image = $image ?: get_theme_file_uri('/images/logo.jpg'); // Default share image
    $description = $description ?: "Check out our amazing daycare services!"; // Default description
    
    // Encode for URLs
    $encodedUrl = urlencode($url);
    $encodedTitle = urlencode($title);
    $encodedDescription = urlencode($description);
    
    // Generate the HTML
    $html = '
    <div class="social-sharing">
        <h3>Share our services</h3>
        <div class="share-buttons">
            <a href="https://www.facebook.com/sharer/sharer.php?u='.$encodedUrl.'&quote='.$encodedDescription.'" 
               target="_blank" class="share-btn facebook" title="Share on Facebook">
                <i class="fab fa-facebook-f"></i>
            </a>
            
            <a href="https://twitter.com/intent/tweet?url='.$encodedUrl.'&text='.$encodedTitle.'&hashtags=Daycare" 
               target="_blank" class="share-btn twitter" title="Share on Twitter">
                <i class="fab fa-twitter"></i>
            </a>
            
            <a href="https://wa.me/?text='.$encodedTitle.'%20'.$encodedUrl.'" 
               target="_blank" class="share-btn whatsapp" title="Share on WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
            
            <a href="mailto:?subject='.rawurlencode($title).'&body='.$encodedDescription.'%20'.$encodedUrl.'" 
               class="share-btn email" title="Share via Email">
                <i class="fas fa-envelope"></i>
            </a>
            
            <a href="#" class="share-btn link tooltip" title="Copy link" onclick="navigator.clipboard.writeText(\''.addslashes($url).'\');return false;">
                <i class="fas fa-link"></i>
                <span class="tooltiptext">Copy to clipboard</span>
            </a>
        </div>
    </div>
    
    <style>
        /* Include the same CSS as in the first example */
    </style>
    ';
    
    return $html;
}
    </script>
</body>
</html>