<?php
/**
 * Plugin Name: Interactive Micro Slider
 * Plugin URI: https://janieart.com/
 * Description: Create beautiful sliders with smooth micro-interactions, animations, and advanced controls. Features touch/swipe support, auto-play, and responsive design.
 * Version: 2.0.0
 * Author: Janie Giltner
 * License: MIT
 * Text Domain: interactive-micro-slider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IMS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('IMS_VERSION', '2.0.0');

class InteractiveMicroSlider {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain
        load_plugin_textdomain('interactive-micro-slider', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_ims_save_slider', array($this, 'save_slider'));
        add_action('wp_ajax_ims_delete_slider', array($this, 'delete_slider'));
        add_shortcode('interactive_slider', array($this, 'display_slider'));
        
        // Register custom post type for sliders
        $this->register_slider_post_type();
    }
    
    public function activate() {
        // Create database table for slider data
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ims_sliders';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            slides longtext NOT NULL,
            settings longtext NOT NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create default slider
        $default_slides = json_encode(array(
            array(
                'image' => IMS_PLUGIN_URL . 'assets/demo1.jpg',
                'title' => 'Welcome to Interactive Slider',
                'subtitle' => 'Create Amazing Presentations',
                'description' => 'Build stunning sliders with smooth micro-interactions and professional animations.',
                'button_text' => 'Get Started',
                'button_url' => '#',
                'overlay_opacity' => '0.4'
            ),
            array(
                'image' => IMS_PLUGIN_URL . 'assets/demo2.jpg',
                'title' => 'Responsive Design',
                'subtitle' => 'Works on All Devices',
                'description' => 'Your sliders look perfect on desktop, tablet, and mobile devices.',
                'button_text' => 'Learn More',
                'button_url' => '#',
                'overlay_opacity' => '0.5'
            ),
            array(
                'image' => IMS_PLUGIN_URL . 'assets/demo3.jpg',
                'title' => 'Easy to Customize',
                'subtitle' => 'No Coding Required',
                'description' => 'Simple interface to create professional sliders in minutes.',
                'button_text' => 'Try Now',
                'button_url' => '#',
                'overlay_opacity' => '0.3'
            )
        ));
        
        $default_settings = json_encode(array(
            'autoplay' => true,
            'autoplay_delay' => 5000,
            'transition_speed' => 800,
            'show_dots' => true,
            'show_arrows' => true,
            'show_progress' => true,
            'pause_on_hover' => true,
            'infinite_loop' => true,
            'height' => '500px',
            'animation_type' => 'slide',
            'color_scheme' => 'light'
        ));
        
        $wpdb->insert(
            $table_name,
            array(
                'name' => 'Demo Slider',
                'slides' => $default_slides,
                'settings' => $default_settings
            )
        );
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('ims-slider-script', IMS_PLUGIN_URL . 'assets/slider.js', array('jquery'), IMS_VERSION, true);
        wp_enqueue_style('ims-slider-style', IMS_PLUGIN_URL . 'assets/slider.css', array(), IMS_VERSION);
        
        wp_localize_script('ims-slider-script', 'ims_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ims_nonce')
        ));
    }
    
    public function register_slider_post_type() {
        // This can be extended for more complex slider management
    }
    
    public function display_slider($atts) {
        global $wpdb;
        
        $atts = shortcode_atts(array(
            'id' => '1',
            'height' => '',
            'autoplay' => ''
        ), $atts);
        
        $table_name = $wpdb->prefix . 'ims_sliders';
        $slider = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $atts['id']));
        
        if (!$slider) {
            return '<p>Slider not found.</p>';
        }
        
        $slides = json_decode($slider->slides, true);
        $settings = json_decode($slider->settings, true);
        
        // Override settings with shortcode attributes
        if ($atts['height']) $settings['height'] = $atts['height'];
        if ($atts['autoplay']) $settings['autoplay'] = ($atts['autoplay'] === 'true');
        
        $slider_id = 'ims-slider-' . $slider->id . '-' . uniqid();
        $color_scheme = $settings['color_scheme'] ?? 'light';
        
        ob_start();
        ?>
<div class="ims-slider-container ims-theme-<?php echo esc_html( $color_scheme ); ?>" 
     id="<?php echo esc_attr( $slider_id ); ?>" 
     data-settings='<?php echo esc_attr( wp_json_encode( $settings ) ); ?>' 
     style="height: <?php echo esc_attr( $settings['height'] ); ?>;">
            
            <div class="ims-slider-wrapper">
                <div class="ims-slider-track">
                    <?php foreach ($slides as $index => $slide): ?>
                        <div class="ims-slide <?php echo $index === 0 ? 'active' : ''; ?>" 
                             style="background-image: url('<?php echo esc_url($slide['image']); ?>')">
                            
                            <div class="ims-slide-overlay" 
     style="background: rgba(0,0,0,<?php echo esc_attr( $slide['overlay_opacity'] ?? '0.4' ); ?>)"></div>
                            <div class="ims-slide-content">
                                <div class="ims-content-wrapper">
                                    <?php if (!empty($slide['subtitle'])): ?>
                                        <div class="ims-subtitle" data-animation="fadeInUp" data-delay="0.2s">
                                            <?php echo esc_html($slide['subtitle']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($slide['title'])): ?>
                                        <h2 class="ims-title" data-animation="fadeInUp" data-delay="0.4s">
                                            <?php echo esc_html($slide['title']); ?>
                                        </h2>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($slide['description'])): ?>
                                        <p class="ims-description" data-animation="fadeInUp" data-delay="0.6s">
                                            <?php echo esc_html($slide['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($slide['button_text'])): ?>
                                        <div class="ims-button-wrapper" data-animation="fadeInUp" data-delay="0.8s">
                                            <a href="<?php echo esc_url($slide['button_url']); ?>" 
                                               class="ims-button">
                                                <?php echo esc_html($slide['button_text']); ?>
                                                <span class="ims-button-hover"></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($settings['show_arrows']): ?>
                    <button class="ims-nav ims-prev" type="button">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>
                    <button class="ims-nav ims-next" type="button">
                        <svg viewBox="0 0 24 24" width="24" height="24">
                            <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                        </svg>
                    </button>
                <?php endif; ?>
                
                <?php if ($settings['show_progress']): ?>
                    <div class="ims-progress-bar">
                        <div class="ims-progress-fill"></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($settings['show_dots']): ?>
                <div class="ims-dots">
                    <?php for ($i = 0; $i < count($slides); $i++): ?>
                        <button class="ims-dot <?php echo $i === 0 ? 'active' : ''; ?>" 
        data-slide="<?php echo esc_attr($i); ?>"></button>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        /* Color Scheme Variables */
        .ims-theme-light {
            --ims-primary-color: #ff6b6b;
            --ims-primary-hover: #ee5a24;
            --ims-secondary-color: #ffd700;
            --ims-accent-color: #0073aa;
            --ims-nav-bg: rgba(255,255,255,0.9);
            --ims-nav-bg-hover: white;
            --ims-nav-color: #333;
            --ims-nav-color-hover: #ff6b6b;
            --ims-dot-border: rgba(255,255,255,0.5);
            --ims-dot-active: white;
            --ims-progress-bg: rgba(255,255,255,0.3);
            --ims-button-shadow: rgba(255,107,107,0.3);
            --ims-button-shadow-hover: rgba(255,107,107,0.4);
        }
        
        .ims-theme-dark {
            --ims-primary-color: #4ecdc4;
            --ims-primary-hover: #45b7b8;
            --ims-secondary-color: #f39c12;
            --ims-accent-color: #6c5ce7;
            --ims-nav-bg: rgba(30,30,30,0.9);
            --ims-nav-bg-hover: rgba(20,20,20,1);
            --ims-nav-color: #e0e0e0;
            --ims-nav-color-hover: #4ecdc4;
            --ims-dot-border: rgba(224,224,224,0.5);
            --ims-dot-active: #e0e0e0;
            --ims-progress-bg: rgba(224,224,224,0.3);
            --ims-button-shadow: rgba(78,205,196,0.3);
            --ims-button-shadow-hover: rgba(78,205,196,0.4);
        }
        
        .ims-theme-violet {
            --ims-primary-color: #6c5ce7;
            --ims-primary-hover: #5f4fd1;
            --ims-secondary-color: #fd79a8;
            --ims-accent-color: #00b894;
            --ims-nav-bg: rgba(108,92,231,0.9);
            --ims-nav-bg-hover: rgba(95,79,209,1);
            --ims-nav-color: white;
            --ims-nav-color-hover: #fd79a8;
            --ims-dot-border: rgba(108,92,231,0.5);
            --ims-dot-active: white;
            --ims-progress-bg: rgba(108,92,231,0.3);
            --ims-button-shadow: rgba(108,92,231,0.3);
            --ims-button-shadow-hover: rgba(108,92,231,0.4);
        }
        
        /* Main Slider Styles */
        .ims-slider-container {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            background: #f8f9fa;
        }
        
        .ims-slider-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        .ims-slider-track {
            position: relative;
            width: 100%;
            height: 100%;
        }
        
        .ims-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ims-slide.active {
            opacity: 1;
            transform: translateX(0);
            z-index: 2;
        }
        
        .ims-slide.prev {
            transform: translateX(-100%);
        }
        
        .ims-slide-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            transition: background 0.3s ease;
        }
        
        .ims-slide-content {
            position: relative;
            z-index: 3;
            text-align: center;
            color: white;
            max-width: 800px;
            padding: 40px 20px;
        }
        
        .ims-content-wrapper > * {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .ims-slide.active .ims-content-wrapper > * {
            opacity: 1;
            transform: translateY(0);
        }
        
        .ims-subtitle {
            font-size: 16px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
            color: var(--ims-secondary-color);
            transition-delay: 0.2s;
        }
        
        .ims-title {
            font-size: clamp(28px, 5vw, 48px);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            transition-delay: 0.4s;
        }
        
        .ims-description {
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.95;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            transition-delay: 0.6s;
        }
        
        .ims-button-wrapper {
            transition-delay: 0.8s;
        }
        
        .ims-button {
            display: inline-block;
            position: relative;
            padding: 15px 35px;
            background: linear-gradient(45deg, var(--ims-primary-color), var(--ims-primary-hover));
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 25px var(--ims-button-shadow);
        }
        
        .ims-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px var(--ims-button-shadow-hover);
            color: white;
            text-decoration: none;
        }
        
        .ims-button-hover {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .ims-button:hover .ims-button-hover {
            left: 100%;
        }
        
        /* Navigation Arrows */
        .ims-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: var(--ims-nav-bg);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .ims-nav:hover {
            background: var(--ims-nav-bg-hover);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
        }
        
        .ims-nav svg {
            fill: var(--ims-nav-color);
            transition: fill 0.3s ease;
        }
        
        .ims-nav:hover svg {
            fill: var(--ims-nav-color-hover);
        }
        
        .ims-prev {
            left: 20px;
        }
        
        .ims-next {
            right: 20px;
        }
        
        /* Dots Navigation */
        .ims-dots {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 10;
        }
        
        .ims-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid var(--ims-dot-border);
            background: transparent;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .ims-dot::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            width: 6px;
            height: 6px;
            background: var(--ims-dot-active);
            border-radius: 50%;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .ims-dot:hover,
        .ims-dot.active {
            border-color: var(--ims-dot-active);
            transform: scale(1.3);
        }
        
        .ims-dot.active::before,
        .ims-dot:hover::before {
            transform: translate(-50%, -50%) scale(1);
        }
        
        /* Progress Bar */
        .ims-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--ims-progress-bg);
            z-index: 10;
        }
        
        .ims-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--ims-primary-color), var(--ims-secondary-color));
            width: 0;
            transition: width linear;
        }
        
        /* Dark theme specific adjustments */
        .ims-theme-dark .ims-slider-container {
            background: #2c3e50;
        }
        
        .ims-theme-dark .ims-subtitle {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .ims-theme-dark .ims-title,
        .ims-theme-dark .ims-description {
            text-shadow: 2px 2px 8px rgba(0,0,0,0.7);
        }
        
        /* Violet theme specific adjustments */
        .ims-theme-violet .ims-slider-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .ims-theme-violet .ims-subtitle {
            background: linear-gradient(45deg, var(--ims-secondary-color), var(--ims-accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 600;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .ims-slide-content {
                padding: 20px 15px;
            }
            
            .ims-title {
                font-size: 24px;
            }
            
            .ims-description {
                font-size: 16px;
            }
            
            .ims-nav {
                width: 40px;
                height: 40px;
            }
            
            .ims-prev {
                left: 10px;
            }
            
            .ims-next {
                right: 10px;
            }
            
            .ims-dots {
                bottom: 15px;
            }
        }
        
        /* Loading Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Slide Transitions */
        .ims-slide-transition-fade .ims-slide {
            transform: none;
        }
        
        .ims-slide-transition-fade .ims-slide.active {
            transform: none;
        }
        
        .ims-slide-transition-zoom .ims-slide {
            transform: scale(1.1);
            opacity: 0;
        }
        
        .ims-slide-transition-zoom .ims-slide.active {
            transform: scale(1);
            opacity: 1;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            const sliderId = '<?php echo esc_js( $slider_id ); ?>';
            const $slider = $('#' + sliderId);
            const settings = $slider.data('settings');
            const $slides = $slider.find('.ims-slide');
            const $dots = $slider.find('.ims-dot');
            const $progressFill = $slider.find('.ims-progress-fill');
            
            let currentSlide = 0;
            let isTransitioning = false;
            let autoplayTimer = null;
            let progressTimer = null;
            
            function goToSlide(index, direction = 'next') {
                if (isTransitioning || index === currentSlide) return;
                
                isTransitioning = true;
                const $current = $slides.eq(currentSlide);
                const $next = $slides.eq(index);
                
                // Update dots
                $dots.removeClass('active').eq(index).addClass('active');
                
                // Slide transition
                $current.removeClass('active');
                if (direction === 'prev') {
                    $current.addClass('prev');
                } else {
                    $current.removeClass('prev');
                }
                
                setTimeout(() => {
                    $next.addClass('active').removeClass('prev');
                    currentSlide = index;
                    
                    setTimeout(() => {
                        $current.removeClass('prev');
                        isTransitioning = false;
                    }, settings.transition_speed);
                }, 50);
                
                // Reset progress bar
                if (settings.show_progress && settings.autoplay) {
                    startProgress();
                }
            }
            
            function nextSlide() {
                const next = settings.infinite_loop ? 
                    (currentSlide + 1) % $slides.length : 
                    Math.min(currentSlide + 1, $slides.length - 1);
                goToSlide(next, 'next');
            }
            
            function prevSlide() {
                const prev = settings.infinite_loop ? 
                    (currentSlide - 1 + $slides.length) % $slides.length : 
                    Math.max(currentSlide - 1, 0);
                goToSlide(prev, 'prev');
            }
            
            function startAutoplay() {
                if (!settings.autoplay) return;
                
                autoplayTimer = setInterval(() => {
                    nextSlide();
                }, settings.autoplay_delay);
            }
            
            function stopAutoplay() {
                clearInterval(autoplayTimer);
                clearInterval(progressTimer);
                $progressFill.css('width', '0%');
            }
            
            function startProgress() {
                if (!settings.show_progress || !settings.autoplay) return;
                
                clearInterval(progressTimer);
                $progressFill.css({
                    'width': '0%',
                    'transition-duration': settings.autoplay_delay + 'ms'
                });
                
                setTimeout(() => {
                    $progressFill.css('width', '100%');
                }, 100);
            }
            
            // Event Listeners
            $slider.find('.ims-next').on('click', function() {
                stopAutoplay();
                nextSlide();
                if (settings.autoplay) startAutoplay();
            });
            
            $slider.find('.ims-prev').on('click', function() {
                stopAutoplay();
                prevSlide();
                if (settings.autoplay) startAutoplay();
            });
            
            $dots.on('click', function() {
                const index = $(this).data('slide');
                stopAutoplay();
                goToSlide(index);
                if (settings.autoplay) startAutoplay();
            });
            
            // Hover pause
            if (settings.pause_on_hover && settings.autoplay) {
                $slider.on('mouseenter', stopAutoplay);
                $slider.on('mouseleave', () => {
                    startAutoplay();
                    startProgress();
                });
            }
            
            // Touch/Swipe support
            let startX = 0;
            let endX = 0;
            
            $slider.on('touchstart', function(e) {
                startX = e.originalEvent.touches[0].clientX;
            });
            
            $slider.on('touchmove', function(e) {
                e.preventDefault();
                endX = e.originalEvent.touches[0].clientX;
            });
            
            $slider.on('touchend', function() {
                const difference = startX - endX;
                if (Math.abs(difference) > 50) {
                    stopAutoplay();
                    if (difference > 0) {
                        nextSlide();
                    } else {
                        prevSlide();
                    }
                    if (settings.autoplay) startAutoplay();
                }
            });
            
            // Keyboard navigation
            $(document).on('keydown', function(e) {
                if ($slider.is(':hover')) {
                    if (e.key === 'ArrowLeft') {
                        stopAutoplay();
                        prevSlide();
                        if (settings.autoplay) startAutoplay();
                    } else if (e.key === 'ArrowRight') {
                        stopAutoplay();
                        nextSlide();
                        if (settings.autoplay) startAutoplay();
                    }
                }
            });
            
            // Initialize
            startAutoplay();
            if (settings.show_progress && settings.autoplay) {
                startProgress();
            }
            
            // Intersection Observer for performance
            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            if (settings.autoplay && !autoplayTimer) {
                                startAutoplay();
                                startProgress();
                            }
                        } else {
                            stopAutoplay();
                        }
                    });
                });
                
                observer.observe($slider[0]);
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Interactive Sliders', 'interactive-micro-slider'),
            __('Sliders', 'interactive-micro-slider'),
            'manage_options',
            'interactive-micro-slider',
            array($this, 'admin_page'),
            'dashicons-images-alt2',
            30
        );
    }
    
    public function admin_init() {
        wp_enqueue_media();
        wp_enqueue_script('ims-admin', IMS_PLUGIN_URL . 'assets/admin.js', array('jquery'), IMS_VERSION, true);
        wp_enqueue_style('ims-admin', IMS_PLUGIN_URL . 'assets/admin.css', array(), IMS_VERSION);
    }
    
    public function admin_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ims_sliders';
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $slider_id = isset($_GET['slider_id']) ? intval($_GET['slider_id']) : 0;
        
        if ($action === 'edit' || $action === 'new') {
            $this->edit_slider_page($slider_id);
        } else {
            $this->list_sliders_page();
        }
    }
    
    public function list_sliders_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ims_sliders';
        $sliders = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_date DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Interactive Sliders', 'interactive-micro-slider'); ?></h1>
            <a href="?page=interactive-micro-slider&action=new" class="page-title-action"><?php esc_html_e('Add New', 'interactive-micro-slider'); ?></a>
            
            <?php if (empty($sliders)): ?>
                <div class="notice notice-info">
                    <p><?php esc_html_e('No sliders found. Create your first slider!', 'interactive-micro-slider'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'interactive-micro-slider'); ?></th>
                            <th><?php esc_html_e('Shortcode', 'interactive-micro-slider'); ?></th>
                            <th><?php esc_html_e('Slides', 'interactive-micro-slider'); ?></th>
                            <th><?php esc_html_e('Updated', 'interactive-micro-slider'); ?></th>
                            <th><?php esc_html_e('Actions', 'interactive-micro-slider'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sliders as $slider): ?>
                            <?php $slides = json_decode($slider->slides, true); ?>
                            <tr>
                                <td><strong><?php echo esc_html($slider->name); ?></strong></td>
                                <td>
                                   <code>[interactive_slider id="<?php echo esc_attr( $slider->id ); ?>"]</code>
                                    <button class="button-link" 
        onclick="navigator.clipboard.writeText('[interactive_slider id=&quot;<?php echo esc_js( $slider->id ); ?>&quot;]')">
    <?php esc_html_e('Copy', 'interactive-micro-slider'); ?>
</button>
                                </td>
                                <td>
    <?php echo esc_html( count($slides) ); ?> <?php echo esc_html__( 'slides', 'interactive-micro-slider' ); ?>
</td>
                                <td><?php echo esc_html($slider->updated_date); ?></td>
                                <td>
    <a href="?page=interactive-micro-slider&action=edit&slider_id=<?php echo esc_attr( $slider->id ); ?>">
        <?php echo esc_html__( 'Edit', 'interactive-micro-slider' ); ?>
    </a> |
    <a href="#" class="delete-slider" data-id="<?php echo esc_attr( $slider->id ); ?>" style="color: #a00;">
        <?php echo esc_html__( 'Delete', 'interactive-micro-slider' ); ?>
    </a>
</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <div style="margin-top: 30px;">
                <h3><?php echo esc_html__('Usage Instructions', 'interactive-micro-slider'); ?></h3>
                <p><?php echo esc_html__('Copy and paste the shortcode into any post, page, or widget where you want the slider to appear.', 'interactive-micro-slider'); ?></p>

                <p><?php echo esc_html__('Optional parameters:', 'interactive-micro-slider'); ?></p>
           <ul>
    <li><code>[interactive_slider id="1" height="400px"]</code> - <?php echo esc_html__('Custom height', 'interactive-micro-slider'); ?></li>
    <li><code>[interactive_slider id="1" autoplay="false"]</code> - <?php echo esc_html__('Disable autoplay', 'interactive-micro-slider'); ?></li>
</ul>
                
                <h3><?php echo esc_html__('Color Schemes Preview', 'interactive-micro-slider'); ?></h3>
                <div class="ims-color-scheme-preview">
                    <div class="scheme-preview light-scheme">
                        <div class="scheme-name"><?php echo esc_html__('Light Theme', 'interactive-micro-slider'); ?></div>
                        <div class="scheme-colors">
                            <div class="color-swatch" style="background: linear-gradient(45deg, #ff6b6b, #ee5a24);"></div>
                            <div class="color-swatch" style="background: #ffd700;"></div>
                            <div class="color-swatch" style="background: #0073aa;"></div>
                        </div>
                       <div class="scheme-desc"><?php echo esc_html__('Warm orange tones perfect for creative and energetic designs', 'interactive-micro-slider'); ?></div>

                    
                    <div class="scheme-preview dark-scheme">
                       <div class="scheme-name"><?php echo esc_html__('Dark Theme', 'interactive-micro-slider'); ?></div>
                        <div class="scheme-colors">
                            <div class="color-swatch" style="background: linear-gradient(45deg, #4ecdc4, #45b7b8);"></div>
                            <div class="color-swatch" style="background: #f39c12;"></div>
                            <div class="color-swatch" style="background: #6c5ce7;"></div>
                        </div>
<div class="scheme-desc"><?php echo esc_html__('Cool teal colors ideal for modern and tech-focused websites', 'interactive-micro-slider'); ?></div>
</div>

<div class="scheme-preview violet-scheme">
    <div class="scheme-name"><?php echo esc_html__('Professional Violet', 'interactive-micro-slider'); ?></div>
    <div class="scheme-colors">
        <div class="color-swatch" style="background: linear-gradient(45deg, #6c5ce7, #5f4fd1);"></div>
        <div class="color-swatch" style="background: #fd79a8;"></div>
        <div class="color-swatch" style="background: #00b894;"></div>
    </div>
    <div class="scheme-desc"><?php echo esc_html__('Elegant violet hues suitable for business and professional sites', 'interactive-micro-slider'); ?></div>
</div>
                </div>
                
                <style>
                .ims-color-scheme-preview {
                    display: flex;
                    gap: 20px;
                    margin-top: 15px;
                    flex-wrap: wrap;
                }
                
                .scheme-preview {
                    flex: 1;
                    min-width: 200px;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    background: #f9f9f9;
                }
                
                .scheme-name {
                    font-weight: bold;
                    margin-bottom: 10px;
                    font-size: 14px;
                }
                
                .scheme-colors {
                    display: flex;
                    gap: 8px;
                    margin-bottom: 10px;
                }
                
                .color-swatch {
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    border: 2px solid #fff;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                
                .scheme-desc {
                    font-size: 12px;
                    color: #666;
                    line-height: 1.4;
                }
                </style>
            </div>
        </div>
        
        <script>
        <script>
jQuery(document).ready(function($) {
    $('.delete-slider').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php echo esc_js(__('Are you sure you want to delete this slider?', 'interactive-micro-slider')); ?>')) {
            const sliderId = $(this).data('id');
            $.post(ajaxurl, {
                action: 'ims_delete_slider',
                slider_id: sliderId,
               nonce: '<?php echo esc_js(wp_create_nonce('ims_nonce')); ?>'
            }, function() {
                location.reload();
            });
        }
    });
});
        </script>
        <?php
    }
    
    public function edit_slider_page($slider_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ims_sliders';
        $slider = null;
        $slides = array();
        $settings = array(
            'autoplay' => true,
            'autoplay_delay' => 5000,
            'transition_speed' => 800,
            'show_dots' => true,
            'show_arrows' => true,
            'show_progress' => true,
            'pause_on_hover' => true,
            'infinite_loop' => true,
            'height' => '500px',
            'animation_type' => 'slide'
        );
        
        if ($slider_id > 0) {
            $slider = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $slider_id));
            if ($slider) {
                $slides = json_decode($slider->slides, true);
                $settings = array_merge($settings, json_decode($slider->settings, true));
            }
        }
        
        $is_new = !$slider;
        ?>
        <div class="wrap">
            <h1>
    <?php echo esc_html($is_new ? __('Add New Slider', 'interactive-micro-slider') : __('Edit Slider', 'interactive-micro-slider')); ?>
</h1>
            
            <form id="slider-form" method="post">
                <?php wp_nonce_field('ims_nonce', 'ims_nonce_field'); ?>
               <input type="hidden" name="slider_id" value="<?php echo esc_attr($slider_id); ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
    <label for="slider-name"><?php esc_html_e('Slider Name', 'interactive-micro-slider'); ?></label>
</th>
<td>
    <input type="text" id="slider-name" name="slider_name" 
           value="<?php echo $slider ? esc_attr($slider->name) : ''; ?>" 
           class="regular-text" required>
</td>
</tr>
</table>

<h2><?php esc_html_e('Slides', 'interactive-micro-slider'); ?></h2>
                <div id="slides-container">
                    <?php if (empty($slides)): ?>
                        <div class="slide-item" data-index="0">
                            <?php $this->render_slide_form(0, array()); ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($slides as $index => $slide): ?>
                           <div class="slide-item" data-index="<?php echo esc_attr($index); ?>">
    <?php $this->render_slide_form($index, $slide); ?>
</div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <p>
                    <button type="button" id="add-slide" class="button"><?php esc_html_e('Add Slide', 'interactive-micro-slider'); ?></button>
                </p>
                
                <h2><?php esc_html_e('Slider Settings', 'interactive-micro-slider'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Height', 'interactive-micro-slider'); ?></th>
                        <td>
                            <input type="text" name="settings[height]" value="<?php echo esc_attr($settings['height']); ?>" class="small-text">
                            <p class="description"><?php esc_html_e('Slider height (e.g., 500px, 50vh)', 'interactive-micro-slider'); ?></p>
                        </td>
                    </tr>
                    <tr>
                       <th scope="row"><?php esc_html_e('Autoplay', 'interactive-micro-slider'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[autoplay]" value="1" <?php checked($settings['autoplay']); ?>>
                                <?php esc_html_e('Enable autoplay', 'interactive-micro-slider'); ?>
                            </label>
                        </td>
                    </tr>
              <tr>
    <th scope="row"><?php esc_html_e('Autoplay Delay', 'interactive-micro-slider'); ?></th>
    <td>
        <input type="number" name="settings[autoplay_delay]" value="<?php echo esc_attr($settings['autoplay_delay']); ?>" class="small-text" min="1000" step="500">
        <p class="description"><?php esc_html_e('Delay between slides in milliseconds', 'interactive-micro-slider'); ?></p>
    </td>
</tr>
                    <tr>
    <th scope="row"><?php esc_html_e('Transition Speed', 'interactive-micro-slider'); ?></th>
    <td>
        <input type="number" name="settings[transition_speed]" value="<?php echo esc_attr($settings['transition_speed']); ?>" class="small-text" min="200" step="100">
        <p class="description"><?php esc_html_e('Transition speed in milliseconds', 'interactive-micro-slider'); ?></p>
    </td>
</tr>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Navigation', 'interactive-micro-slider'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[show_arrows]" value="1" <?php checked($settings['show_arrows']); ?>>
                                <?php esc_html_e('Show navigation arrows', 'interactive-micro-slider'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="settings[show_dots]" value="1" <?php checked($settings['show_dots']); ?>>
                               <?php esc_html_e('Show dot navigation', 'interactive-micro-slider'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="settings[show_progress]" value="1" <?php checked($settings['show_progress']); ?>>
                             <?php esc_html_e('Show progress bar', 'interactive-micro-slider'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                       <th scope="row"><?php esc_html_e('Color Scheme', 'interactive-micro-slider'); ?></th>
<td>
    <select name="settings[color_scheme]" class="regular-text">
        <option value="light" <?php selected($settings['color_scheme'], 'light'); ?>>
            <?php esc_html_e('Light Theme (Warm Orange)', 'interactive-micro-slider'); ?>
        </option>
                                <option value="dark" <?php selected($settings['color_scheme'], 'dark'); ?>>
    <?php esc_html_e('Dark Theme (Cool Teal)', 'interactive-micro-slider'); ?>
</option>
<option value="violet" <?php selected($settings['color_scheme'], 'violet'); ?>>
    <?php esc_html_e('Professional Violet', 'interactive-micro-slider'); ?>
</option>
                            </select>
                            <p class="description">
    <?php esc_html_e('Choose a color scheme that matches your site design', 'interactive-micro-slider'); ?>
</p>
</td>
</tr>
<tr>
    <th scope="row">
        <?php esc_html_e('Behavior', 'interactive-micro-slider'); ?>
    </th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[pause_on_hover]" value="1" <?php checked($settings['pause_on_hover']); ?>>
                               <?php esc_html_e('Pause on hover', 'interactive-micro-slider'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="settings[infinite_loop]" value="1" <?php checked($settings['infinite_loop']); ?>>
                              <?php esc_html_e('Infinite loop', 'interactive-micro-slider'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
    <button type="submit" class="button-primary">
        <?php echo $is_new ? esc_html__('Create Slider', 'interactive-micro-slider') : esc_html__('Update Slider', 'interactive-micro-slider'); ?>
    </button>
    <a href="?page=interactive-micro-slider" class="button"><?php esc_html_e('Cancel', 'interactive-micro-slider'); ?></a>
</p>
            </form>
        </div>
        
        <style>
        .slide-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .slide-item h3 {
            margin-top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .slide-controls {
            display: flex;
            gap: 10px;
        }
        
        .slide-preview {
            width: 100px;
            height: 60px;
            background-size: cover;
            background-position: center;
            border-radius: 4px;
            border: 2px solid #ddd;
        }
        
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .image-upload-area:hover {
            border-color: #0073aa;
            background: #f0f8ff;
        }
        
        .image-upload-area.has-image {
            padding: 0;
            border: none;
            background: none;
        }
        
        .uploaded-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let slideIndex = <?php echo count($slides); ?>;
            
            // Add new slide
            $('#add-slide').on('click', function() {
                const slideHtml = `
                    <div class="slide-item" data-index="${slideIndex}">
                        <h3>
                            <?php esc_html_e('Slide', 'interactive-micro-slider'); ?> ${slideIndex + 1}
                            <div class="slide-controls">
                                <button type="button" class="button move-up">↑</button>
                                <button type="button" class="button move-down">↓</button>
                                <button type="button" class="button button-link-delete remove-slide"><?php esc_html_e('Remove', 'interactive-micro-slider'); ?></button>
</div>
</h3>
<table class="form-table">
    <tr>
        <th><?php esc_html_e('Background Image', 'interactive-micro-slider'); ?></th>
        <td>
                  <div class="image-upload-area" data-index="${slideIndex}">
    <p><?php esc_html_e('Click to upload image', 'interactive-micro-slider'); ?></p>
</div>
<input type="hidden" name="slides[${slideIndex}][image]" class="slide-image-url">
</td>
</tr>
<tr>
    <th><?php esc_html_e('Title', 'interactive-micro-slider'); ?></th>
    <td><input type="text" name="slides[${slideIndex}][title]" class="regular-text"></td>
</tr>
<tr>
    <th><?php esc_html_e('Subtitle', 'interactive-micro-slider'); ?></th>
                                <td><input type="text" name="slides[${slideIndex}][subtitle]" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Description', 'interactive-micro-slider'); ?></th>
<td><textarea name="slides[${slideIndex}][description]" rows="3" class="large-text"></textarea></td>
</tr>
<tr>
    <th><?php esc_html_e('Button Text', 'interactive-micro-slider'); ?></th>
    <td><input type="text" name="slides[${slideIndex}][button_text]" class="regular-text"></td>
</tr>
                            <tr>
                               <th><?php esc_html_e('Button URL', 'interactive-micro-slider'); ?></th>
<td><input type="url" name="slides[${slideIndex}][button_url]" class="regular-text"></td>
</tr>
<tr>
    <th><?php esc_html_e('Overlay Opacity', 'interactive-micro-slider'); ?></th>
    <td>
        <input type="range" name="slides[${slideIndex}][overlay_opacity]" min="0" max="1" step="0.1" value="0.4" class="opacity-slider">
        <span class="opacity-value">0.4</span>
    </td>
                            </tr>
                        </table>
                    </div>
                `;
                $('#slides-container').append(slideHtml);
                slideIndex++;
                updateSlideNumbers();
            });
            
            // Remove slide
            $(document).on('click', '.remove-slide', function() {
                if ($('.slide-item').length > 1) {
                    $(this).closest('.slide-item').remove();
                    updateSlideNumbers();
                } else {
                   alert('<?php echo esc_js(__('You must have at least one slide.', 'interactive-micro-slider')); ?>');
 'interactive-micro-slider'); ?>');
                }
            });
            
            // Move slides up/down
            $(document).on('click', '.move-up', function() {
                const $slide = $(this).closest('.slide-item');
                $slide.prev('.slide-item').before($slide);
                updateSlideNumbers();
            });
            
            $(document).on('click', '.move-down', function() {
                const $slide = $(this).closest('.slide-item');
                $slide.next('.slide-item').after($slide);
                updateSlideNumbers();
            });
            
            // Media uploader
            $(document).on('click', '.image-upload-area', function() {
                const $area = $(this);
                const $input = $area.siblings('.slide-image-url');
                
                const frame = wp.media({
                   title: '<?php echo esc_js(__('Select Image', 'interactive-micro-slider')); ?>',
                 button: { text: '<?php echo esc_js(__('Use Image', 'interactive-micro-slider')); ?>' },
multiple: false
                });
                
                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url);
                    $area.addClass('has-image').html(`<img src="${attachment.url}" class="uploaded-image" alt="">`);
                });
                
                frame.open();
            });
            
            // Opacity slider
            $(document).on('input', '.opacity-slider', function() {
                $(this).siblings('.opacity-value').text($(this).val());
            });
            
            // Update slide numbers
            function updateSlideNumbers() {
                $('.slide-item').each(function(index) {
                    $(this).attr('data-index', index);
                    $(this).find('h3').first().contents().first().replaceWith('<?php echo esc_js(__('Slide', 'interactive-micro-slider')); ?> ' + (index + 1) + ' ');
                    
                    // Update input names
                    $(this).find('input, textarea').each(function() {
                        const name = $(this).attr('name');
                        if (name && name.includes('slides[')) {
                            $(this).attr('name', name.replace(/slides\[\d+\]/, `slides[${index}]`));
                        }
                    });
                });
            }
            
            // Form submission
            $('#slider-form').on('submit', function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize() + '&action=ims_save_slider';
                
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        window.location.href = '?page=interactive-micro-slider&message=saved';
                    } else {
                        alert(response.data.message || '<?php echo esc_js(__('Error saving slider.', 'interactive-micro-slider')); ?>');
}
}).fail(function() {
    alert('<?php echo esc_js(__('Error saving slider.', 'interactive-micro-slider')); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    private function render_slide_form($index, $slide) {
        $slide = wp_parse_args($slide, array(
            'image' => '',
            'title' => '',
            'subtitle' => '',
            'description' => '',
            'button_text' => '',
            'button_url' => '',
            'overlay_opacity' => '0.4'
        ));
        ?>
        <h3>
          <?php echo esc_html__('Slide', 'interactive-micro-slider') . ' ' . esc_html($index + 1); ?>
            <div class="slide-controls">
                <?php if ($index > 0): ?>
                    <button type="button" class="button move-up">↑</button>
                <?php endif; ?>
                <button type="button" class="button move-down">↓</button>
                <button type="button" class="button button-link-delete remove-slide"><?php echo esc_html__('Remove', 'interactive-micro-slider'); ?></button>
            </div>
        </h3>
        
        <table class="form-table">
            <tr>
              <th><?php echo esc_html__('Background Image', 'interactive-micro-slider'); ?></th>
                <td>
                   <div class="image-upload-area <?php echo $slide['image'] ? 'has-image' : ''; ?>" data-index="<?php echo esc_attr($index); ?>">
    <?php if ($slide['image']): ?>
        <img src="<?php echo esc_url($slide['image']); ?>" class="uploaded-image" alt="">
    <?php else: ?>
        <p><?php echo esc_html__('Click to upload image', 'interactive-micro-slider'); ?></p>
    <?php endif; ?>
</div>
                    <input type="hidden" name="slides[<?php echo esc_attr($index); ?>][image]" class="slide-image-url" value="<?php echo esc_attr($slide['image']); ?>">

                </td>
            </tr>
            <tr>
                <th><?php echo esc_html__('Title', 'interactive-micro-slider'); ?></th>
               <td><input type="text" name="slides[<?php echo esc_attr($index); ?>][title]" value="<?php echo esc_attr($slide['title']); ?>" class="regular-text"></td>

            </tr>
            <tr>
              <th><?php echo esc_html__('Subtitle', 'interactive-micro-slider'); ?></th>
                <td><input type="text" name="slides[<?php echo esc_attr($index); ?>][subtitle]" value="<?php echo esc_attr($slide['subtitle']); ?>" class="regular-text"></td>

            </tr>
            <tr>
               <th><?php echo esc_html__('Description', 'interactive-micro-slider'); ?></th>
               <td><textarea name="slides[<?php echo esc_attr($index); ?>][description]" rows="3" class="large-text"><?php echo esc_textarea($slide['description']); ?></textarea></td>

            </tr>
            <tr>
                <th><?php echo esc_html__('Button Text', 'interactive-micro-slider'); ?></th>
               <td><input type="text" name="slides[<?php echo esc_attr($index); ?>][button_text]" value="<?php echo esc_attr($slide['button_text']); ?>" class="regular-text"></td>

            </tr>
            <tr>
                <th><?php echo esc_html__('Button URL', 'interactive-micro-slider'); ?></th>
               <td><input type="url" name="slides[<?php echo esc_attr($index); ?>][button_url]" value="<?php echo esc_attr($slide['button_url']); ?>" class="regular-text"></td>

            </tr>
            <tr>
                <th><?php echo esc_html__('Overlay Opacity', 'interactive-micro-slider'); ?></th>
                <td>
                    <input type="range" name="slides[<?php echo esc_attr($index); ?>][overlay_opacity]" min="0" max="1" step="0.1" value="<?php echo esc_attr($slide['overlay_opacity']); ?>" class="opacity-slider">

                    <span class="opacity-value"><?php echo esc_html($slide['overlay_opacity']); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function save_slider() {
        check_ajax_referer('ims_nonce', 'ims_nonce_field');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'interactive-micro-slider')));
        }
        
        $slider_id = intval($_POST['slider_id']);
        $slider_name = sanitize_text_field($_POST['slider_name']);
        $slides = isset($_POST['slides']) ? $_POST['slides'] : array();
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        // Sanitize slides data
        $clean_slides = array();
        foreach ($slides as $slide) {
            $clean_slides[] = array(
                'image' => esc_url_raw($slide['image']),
                'title' => sanitize_text_field($slide['title']),
                'subtitle' => sanitize_text_field($slide['subtitle']),
                'description' => sanitize_textarea_field($slide['description']),
                'button_text' => sanitize_text_field($slide['button_text']),
                'button_url' => esc_url_raw($slide['button_url']),
                'overlay_opacity' => floatval($slide['overlay_opacity'])
            );
        }
        
        // Sanitize settings
        $clean_settings = array(
            'autoplay' => isset($settings['autoplay']),
            'autoplay_delay' => max(1000, intval($settings['autoplay_delay'])),
            'transition_speed' => max(200, intval($settings['transition_speed'])),
            'show_dots' => isset($settings['show_dots']),
            'show_arrows' => isset($settings['show_arrows']),
            'show_progress' => isset($settings['show_progress']),
            'pause_on_hover' => isset($settings['pause_on_hover']),
            'infinite_loop' => isset($settings['infinite_loop']),
            'height' => sanitize_text_field($settings['height']),
            'animation_type' => 'slide',
            'color_scheme' => sanitize_text_field($settings['color_scheme'] ?? 'light')
        );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ims_sliders';
        
        $data = array(
            'name' => $slider_name,
            'slides' => json_encode($clean_slides),
            'settings' => json_encode($clean_settings)
        );
        
        if ($slider_id > 0) {
            // Update existing slider
            $result = $wpdb->update($table_name, $data, array('id' => $slider_id));
        } else {
            // Create new slider
            $result = $wpdb->insert($table_name, $data);
            $slider_id = $wpdb->insert_id;
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Slider saved successfully.', 'interactive-micro-slider'),
                'slider_id' => $slider_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to save slider.', 'interactive-micro-slider')));
        }
    }
    
    public function delete_slider() {
        check_ajax_referer('ims_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'interactive-micro-slider')));
        }
        
        $slider_id = intval($_POST['slider_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ims_sliders';
        
        $result = $wpdb->delete($table_name, array('id' => $slider_id));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => __('Failed to delete slider.', 'interactive-micro-slider')));
        }
    }
}

// Initialize the plugin

new InteractiveMicroSlider();
