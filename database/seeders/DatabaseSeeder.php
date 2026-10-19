<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedMenus();
        $this->seedPages();
        $this->seedDraftContent();
        $this->call(AdminUserSeeder::class);
    }

    private function seedSettings(): void
    {
        $settings = [
            'site_name'=>['KELIKA','general'], 'site_tagline'=>['Driven by the elements. Built for stronger athletes and a brighter tomorrow.','general'],
            'contact_phone'=>['7874028876','contact'], 'contact_email'=>['','contact'], 'contact_notification_email'=>['','contact'], 'contact_address'=>['','contact'], 'opening_hours'=>['','contact'], 'map_url'=>['','contact'],
            'booking_url'=>['','general'], 'booking_button_label'=>['Book a Court','general'],
            'primary_color'=>['#064f43','branding'], 'terracotta_color'=>['#cf6d50','branding'], 'gold_color'=>['#d4a35d','branding'], 'font_family'=>['Inter','branding'], 'button_style'=>['square','branding'],
            'logo_url'=>['/images/kelika-wordmark.png','branding'], 'footer_logo_url'=>['/images/kelika-wordmark.png','branding'], 'mobile_logo_url'=>['/images/kelika-wordmark.png','branding'], 'favicon_url'=>['','branding'], 'social_image_url'=>['','branding'],
            'earth_color'=>['#176b4e','branding'], 'water_color'=>['#1687c9','branding'], 'fire_color'=>['#d94737','branding'], 'air_color'=>['#38a9ce','branding'], 'space_color'=>['#8c45df','branding'], 'instagram_url'=>['','social'], 'facebook_url'=>['','social'], 'youtube_url'=>['','social'], 'linkedin_url'=>['','social'],
        ];
        foreach ($settings as $key=>[$value,$group]) Setting::query()->firstOrCreate(['key'=>$key],['value'=>$value,'group'=>$group]);
    }

    private function seedMenus(): void
    {
        foreach ([
            'main'=>['name'=>'Main Navigation','items'=>[['Home','/'],['Academy','/academy'],['Sports','/sports'],['Programs','/programs'],['Facilities','/facilities'],['Events','/events'],['About','/about'],['Contact','/contact']]],
            'footer'=>['name'=>'Footer Quick Links','items'=>[['Home','/'],['Academy','/academy'],['Sports','/sports'],['Programs','/programs'],['Facilities','/facilities'],['Events','/events'],['About','/about'],['Contact','/contact']]],
        ] as $location=>$config) {
            $menu=Menu::query()->firstOrCreate(['location'=>$location],['name'=>$config['name'],'is_active'=>true]);
            if (!$menu->menuItems()->exists()) foreach($config['items'] as $index=>[$label,$url]) $menu->menuItems()->create(['label'=>$label,'url'=>$url,'target'=>'_self','is_visible'=>true,'sort_order'=>$index+1]);
        }
    }

    private function seedPages(): void
    {
        $pages = [
            'home'=>['Home',[
                ['hero','Home hero',['eyebrow'=>'DISCIPLINE  |  HEALTH  |  COMMUNITY','image'=>'https://images.unsplash.com/photo-1722087642932-9b070e9a066e?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Badminton player leaping for a shot on an indoor court','image_position'=>'center','title'=>'PLAY|GROW|BELONG.','subtitle'=>'World-class badminton training, modern facilities and a vibrant sports community for all ages.','button_label'=>'Join Academy','button_url'=>'/academy','secondary_button_label'=>'Book a Court','secondary_button_url'=>'','tone'=>'light']],
                ['card_grid','Programs overview',['eyebrow'=>'OUR PROGRAMS','title'=>'FOR EVERY STAGE|OF YOUR JOURNEY','subtitle'=>'Structured training for different ages, goals and experience levels.','button_label'=>'View All Programs','button_url'=>'/programs','items'=>[['title'=>'Kids Program','description'=>'Build skills, confidence and a lifelong love for sports.','url'=>'/programs'],['title'=>'Youth Development','description'=>'Structured training for consistent improvement.','url'=>'/programs'],['title'=>'Performance Training','description'=>'Advanced coaching for competitive players.','url'=>'/programs'],['title'=>'Adult Coaching','description'=>'Stay active, healthy and sharpen your game.','url'=>'/programs']]]],
                ['split','Facilities overview',['eyebrow'=>'OUR FACILITIES','image'=>'https://images.unsplash.com/photo-1775993167284-8e6a6e56ab69?auto=format&fit=crop&w=1400&q=82','image_alt'=>'Indoor badminton sports hall with blue flooring','title'=>'PREMIUM SPACES|FOR A BETTER GAME','body'=>'Purpose-built sports spaces, quality equipment and thoughtful amenities designed to support athletes at every level.','button_label'=>'Explore Facilities','button_url'=>'/facilities']],
                ['feature_list','Why KELIKA',['eyebrow'=>'WHY KELIKA','title'=>'MORE THAN|JUST ACADEMY','body'=>'We combine coaching, facilities and a supportive community to help people become stronger, healthier and more confident.','items'=>[['icon'=>'✦','title'=>'Experienced Coaches','description'=>'Learn from qualified and professional coaches.'],['icon'=>'↗','title'=>'Personalized Training','description'=>'Programs for different goals and skill levels.'],['icon'=>'❋','title'=>'Holistic Development','description'=>'Physical, mental and character growth.'],['icon'=>'♧','title'=>'Inclusive Community','description'=>'A welcoming space for all ages.'],['icon'=>'♜','title'=>'Competitive Opportunities','description'=>'Tournaments and ranking events where offered.'],['icon'=>'☆','title'=>'Focus on Life Skills','description'=>'Discipline, confidence and teamwork.']]]],
                ['stats','Verified statistics',['eyebrow'=>'KELIKA AT A GLANCE','title'=>'','items'=>[]]],
                ['event_list','Upcoming events',['eyebrow'=>'UPCOMING EVENTS','title'=>'BE A PART OF|THE ACTION','button_label'=>'View All Events','button_url'=>'/events']],
                ['testimonials','Community feedback',['eyebrow'=>'WHAT OUR COMMUNITY SAYS','title'=>'REAL PEOPLE.|REAL PROGRESS.']],
                ['cta','Final call to action',['eyebrow'=>'READY TO BEGIN?','title'=>'TRAIN. COMPETE. GROW.','subtitle'=>'Be part of KELIKA and unlock your potential through quality facilities, expert coaching and a supportive community.','button_label'=>'Join Academy','button_url'=>'/academy','secondary_button_label'=>'Book a Court','secondary_button_url'=>'','tone'=>'green']],
            ]],
            'academy'=>['Academy',[
                ['hero','Academy introduction',['eyebrow'=>'KELIKA ACADEMY','image'=>'https://images.unsplash.com/photo-1779608103749-395f76f42f94?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Adults and a child sharing a badminton session','image_position'=>'center','title'=>'Build Skills.|Build Character.','subtitle'=>'A structured, supportive sports environment where athletes can learn, improve and grow at every stage.','button_label'=>'Explore Programs','button_url'=>'/programs','secondary_button_label'=>'Contact Us','secondary_button_url'=>'/contact']],
                ['feature_list','Academy values',['eyebrow'=>'OUR APPROACH','title'=>'GROW BEYOND|THE GAME','body'=>'Our approach brings together coaching, physical development, confidence and community.','items'=>[['title'=>'Mission','description'=>'Inspire and develop stronger, healthier and more confident individuals through sports.','icon'=>'◎'],['title'=>'Vision','description'=>'Build a welcoming multi-sport community focused on continuous growth.','icon'=>'◇'],['title'=>'Values','description'=>'Discipline, respect, inclusion, learning and a passion for sports.','icon'=>'✦']]]],
                ['program_list','Academy programs',['eyebrow'=>'TRAINING PATHWAYS','title'=>'PROGRAMS FOR EVERY STAGE']],
                ['split','Coaching philosophy',['eyebrow'=>'COACHING THAT CONNECTS','title'=>'SUPPORTIVE COACHING.|MEANINGFUL PROGRESS.','body'=>'Programs should be adapted to each athlete’s age, experience, goals and readiness. Confirm coach credentials and program availability before publication.','button_label'=>'View Programs','button_url'=>'/programs']],
                ['five_elements','Five elements',['eyebrow'=>'OUR PHILOSOPHY','title'=>'GUIDED BY THE|FIVE ELEMENTS','subtitle'=>'A balanced approach to athlete development inspired by Earth, Water, Fire, Air and Space.','items'=>[['title'=>'Earth','keyword'=>'Stability','description'=>'Strong foundations and discipline.','color'=>'#176b4e','icon'=>'♧'],['title'=>'Water','keyword'=>'Flow','description'=>'Adaptability and continuous improvement.','color'=>'#1687c9','icon'=>'≈'],['title'=>'Fire','keyword'=>'Energy','description'=>'Passion, motivation and drive.','color'=>'#d94737','icon'=>'♨'],['title'=>'Air','keyword'=>'Freedom','description'=>'Focus, agility and mental strength.','color'=>'#38a9ce','icon'=>'≋'],['title'=>'Space','keyword'=>'Possibility','description'=>'Growth, awareness and potential.','color'=>'#8c45df','icon'=>'✧']]]],
                ['cta','Join academy',['eyebrow'=>'YOUR JOURNEY STARTS HERE','title'=>'FIND YOUR PLACE AT KELIKA','subtitle'=>'Speak with the team to find a suitable program.','button_label'=>'Contact Us','button_url'=>'/contact','tone'=>'green']],
            ]],
            'sports'=>['Sports',[
                ['hero','Sports page hero',['eyebrow'=>'OUR SPORTS','image'=>'https://images.unsplash.com/photo-1780233689566-55045608bd17?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Badminton players seen through a racket on an indoor court','image_position'=>'center','title'=>'Different Sports.|Stronger Individuals.','subtitle'=>'At KELIKA, explore sports and training pathways designed to help athletes of all ages build skills, fitness and confidence.','button_label'=>'Explore Our Sports','button_url'=>'#sports-list']],
                ['feature_list','Sports benefits',['eyebrow'=>'MORE THAN THE GAME','title'=>'COACHING. FACILITIES.|COMMUNITY.','items'=>[['title'=>'Professional Coaching','description'=>'Structured support for athletes.','icon'=>'♧'],['title'=>'World-class Facilities','description'=>'A safe and inspiring environment.','icon'=>'▦'],['title'=>'Structured Programs','description'=>'Options for different skill levels.','icon'=>'⇧'],['title'=>'Tournaments & Events','description'=>'Competition opportunities where offered.','icon'=>'♜'],['title'=>'Community for All Ages','description'=>'A place to learn and grow together.','icon'=>'♧']]]],
                ['sport_directory','Sports directory',['eyebrow'=>'FEATURED SPORTS','title'=>'FIND YOUR GAME']],
                ['card_grid','More sports coming soon',['eyebrow'=>'EXPANDING SOON','title'=>'MORE SPORTS|MORE OPPORTUNITIES','items'=>[['title'=>'Basketball','description'=>'Availability to be confirmed.'],['title'=>'Tennis','description'=>'Availability to be confirmed.'],['title'=>'Volleyball','description'=>'Availability to be confirmed.'],['title'=>'Athletics','description'=>'Availability to be confirmed.']]]],
                ['cta','Choose a sport',['eyebrow'=>'NEED GUIDANCE?','title'=>'WHICH SPORT IS RIGHT FOR YOU?','subtitle'=>'Tell us about your interests, goals and experience and our team can guide you.','button_label'=>'Talk to Our Team','button_url'=>'/contact','tone'=>'light']],
            ]],
            'programs'=>['Programs',[
                ['hero','Programs hero',['eyebrow'=>'PROGRAMS','image'=>'https://images.unsplash.com/photo-1774652995555-59fa40d9306c?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Athlete practicing badminton on an indoor court','image_position'=>'center','title'=>'Structured Programs|for Every Stage','subtitle'=>'From first steps to competitive development, programs are designed to build skills, confidence, fitness and character.','button_label'=>'Explore Programs','button_url'=>'#program-list','secondary_button_label'=>'Contact Us','secondary_button_url'=>'/contact']],
                ['program_list','Programs directory',['eyebrow'=>'OUR PROGRAMS','title'=>'CHOOSE YOUR PATH']],
                ['split','Kids program detail',['eyebrow'=>'KIDS PROGRAM · AGES 5–12','title'=>'BUILD A LOVE|FOR THE GAME','body'=>'A safe and engaging environment can help children develop fundamental skills, fitness, confidence and a lifelong love for sports. Confirm the final age ranges and program availability before publication.','button_label'=>'Enquire About Programs','button_url'=>'/contact']],
                ['feature_list','What athletes learn',['eyebrow'=>'PROGRAM DETAILS','title'=>'MORE THAN|TECHNIQUE','items'=>[['title'=>'Basic Techniques','description'=>'Grip, stance, footwork and basic strokes.','icon'=>'♧'],['title'=>'Game Understanding','description'=>'Rules, scoring and awareness.','icon'=>'▦'],['title'=>'Physical Fitness','description'=>'Agility, balance, coordination and stamina.','icon'=>'↗'],['title'=>'Values & Life Skills','description'=>'Discipline, teamwork and confidence.','icon'=>'✦']]]],
                ['card_grid','Training progression',['eyebrow'=>'TRAINING STRUCTURE','title'=>'A CLEAR PATHWAY|FOR GROWTH','items'=>[['title'=>'Foundation','description'=>'Basic skills and game familiarity.'],['title'=>'Development','description'=>'Improved technique and match play.'],['title'=>'Advanced','description'=>'Higher intensity and tactical learning.'],['title'=>'Competitive','description'=>'Tournament preparation where available.']]]],
                ['cta','Programs CTA',['eyebrow'=>'READY TO GET STARTED?','title'=>'FIND A PROGRAM THAT FITS','subtitle'=>'Ask the KELIKA team about age groups, coaching options and availability.','button_label'=>'Contact Us','button_url'=>'/contact','tone'=>'green']],
            ]],
            'facilities'=>['Facilities',[
                ['hero','Facilities hero',['eyebrow'=>'OUR FACILITIES','image'=>'https://images.unsplash.com/photo-1775993167284-8e6a6e56ab69?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Indoor badminton sports hall with blue courts','image_position'=>'center','title'=>'World-class Facilities|for Every Athlete','subtitle'=>'Train, play and grow in a modern, safe and inspiring environment. Final facility details will be confirmed before launch.','button_label'=>'Explore Facilities','button_url'=>'#facility-list','secondary_button_label'=>'Book a Court','secondary_button_url'=>'']],
                ['split','Badminton courts',['eyebrow'=>'MAIN ATTRACTION','title'=>'PROFESSIONAL|BADMINTON COURTS','body'=>'Showcase verified information about court surfaces, lighting, equipment and availability. Do not publish certification or capacity claims unless confirmed.','button_label'=>'Book a Court','button_url'=>'']],
                ['facilities_showcase','Amenities',['eyebrow'=>'BEYOND THE COURTS','title'=>'MODERN AMENITIES|FOR A COMPLETE EXPERIENCE','subtitle'=>'Add only amenities that are available at the KELIKA facility.']],
                ['card_grid','Additional spaces',['eyebrow'=>'ADDITIONAL AREAS','title'=>'SPACES TO SUPPORT|EVERY NEED','items'=>[['title'=>'Training Zone','description'=>'Availability and details to be confirmed.'],['title'=>'Viewing Area','description'=>'Availability and details to be confirmed.'],['title'=>'Multi-purpose Space','description'=>'Availability and details to be confirmed.'],['title'=>'Meeting Room','description'=>'Availability and details to be confirmed.']]]],
                ['cta','Facility CTA',['eyebrow'=>'EXPERIENCE KELIKA','title'=>'VISIT OUR FACILITIES','subtitle'=>'Contact our team to arrange a visit or ask about court availability.','button_label'=>'Contact Us','button_url'=>'/contact','secondary_button_label'=>'Book a Court','secondary_button_url'=>'','tone'=>'green']],
            ]],
            'events'=>['Events',[
                ['hero','Events hero',['eyebrow'=>'EVENTS','image'=>'https://images.unsplash.com/photo-1783266503722-973f579d9b5a?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Players on a blue indoor badminton court','image_position'=>'center','title'=>'Tournaments, Camps|& Community Events','subtitle'=>'Events can help players learn, compete and connect. Check the latest confirmed schedule here.','button_label'=>'Explore Events','button_url'=>'#upcoming-events','secondary_button_label'=>'Contact Us','secondary_button_url'=>'/contact']],
                ['event_list','Upcoming events',['eyebrow'=>'UPCOMING EVENTS','title'=>'JOIN THE ACTION']],
                ['feature_list','Why participate',['eyebrow'=>'WHY PARTICIPATE?','title'=>'LEARN. COMPETE.|CONNECT.','items'=>[['title'=>'Improve Skills','description'=>'Gain match experience and learn from others.','icon'=>'◎'],['title'=>'Build Confidence','description'=>'Develop a positive competitive mindset.','icon'=>'♧'],['title'=>'Be Part of a Community','description'=>'Meet fellow players and make friends.','icon'=>'♜'],['title'=>'Pathway to Growth','description'=>'Explore development opportunities.','icon'=>'↗']]]],
                ['gallery','Event gallery',['eyebrow'=>'EVENT GALLERY','title'=>'MOMENTS THAT|INSPIRE']],
                ['cta','Events CTA',['eyebrow'=>'BE PART OF OUR NEXT EVENT','title'=>'PLAY. COMPETE. CONNECT.','subtitle'=>'Contact KELIKA to learn about upcoming events and participation.','button_label'=>'Contact Us','button_url'=>'/contact','tone'=>'light']],
            ]],
            'about'=>['About',[
                ['hero','About hero',['eyebrow'=>'ABOUT KELIKA','image'=>'https://images.unsplash.com/photo-1722087642932-9b070e9a066e?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Badminton player in action representing focus and determination','image_position'=>'center','title'=>'Driven by the Elements.|Built for a Brighter Tomorrow.','subtitle'=>'KELIKA is a sports academy concept focused on developing athletes through coaching, community and a holistic approach to growth. Confirm all operational claims before launch.','button_label'=>'Our Story','button_url'=>'#our-story']],
                ['feature_list','Mission vision values',['eyebrow'=>'OUR PURPOSE','title'=>'A COMMUNITY|BUILT TO GROW','items'=>[['title'=>'Our Mission','description'=>'Inspire and develop stronger, healthier and more confident individuals through sports.','icon'=>'◎'],['title'=>'Our Vision','description'=>'Build a welcoming sports community focused on excellence and growth.','icon'=>'◇'],['title'=>'Our Values','description'=>'Discipline, respect, inclusivity, continuous learning and passion.','icon'=>'✦'],['title'=>'Our Community','description'=>'A supportive environment for athletes and families.','icon'=>'♧']]]],
                ['split','Our story',['eyebrow'=>'OUR STORY','title'=>'A PASSION FOR SPORTS.|A VISION FOR THE FUTURE.','body'=>'Tell the approved KELIKA story here. Replace this draft narrative with the founder-approved history before publishing.']],
                ['five_elements','Five elements',['eyebrow'=>'OUR PHILOSOPHY','title'=>'GUIDED BY THE|FIVE ELEMENTS','subtitle'=>'Each element represents a quality that can contribute to a stronger, healthier and more complete individual.','items'=>[['title'=>'Earth','keyword'=>'Stability','description'=>'Strong foundation and discipline.','color'=>'#176b4e','icon'=>'♧'],['title'=>'Water','keyword'=>'Flow','description'=>'Adaptability and improvement.','color'=>'#1687c9','icon'=>'≈'],['title'=>'Fire','keyword'=>'Energy','description'=>'Passion and motivation.','color'=>'#d94737','icon'=>'♨'],['title'=>'Air','keyword'=>'Freedom','description'=>'Focus, agility and mental strength.','color'=>'#38a9ce','icon'=>'≋'],['title'=>'Space','keyword'=>'Possibility','description'=>'Growth, awareness and potential.','color'=>'#8c45df','icon'=>'✧']]]],
                ['team','Coaching team',['eyebrow'=>'OUR PEOPLE','title'=>'EXPERIENCED COACHES.|REAL GUIDANCE.']],
                ['cta','About CTA',['eyebrow'=>'BE PART OF THE KELIKA JOURNEY','title'=>'FIND YOUR PLACE AT KELIKA','button_label'=>'Contact Us','button_url'=>'/contact','tone'=>'green']],
            ]],
            'contact'=>['Contact',[
                ['hero','Contact hero',['eyebrow'=>'CONTACT US','image'=>'https://images.unsplash.com/photo-1775993167284-8e6a6e56ab69?auto=format&fit=crop&w=1800&q=85','image_alt'=>'Indoor badminton court ready for players','image_position'=>'center','title'=>'Let’s Build a|Stronger Sports Community.','subtitle'=>'We would love to hear from you. Ask about programs, events, court booking links or learning more about KELIKA.','button_label'=>'Send Us a Message','button_url'=>'#contact-form','secondary_button_label'=>'Explore Sports','secondary_button_url'=>'/sports']],
                ['contact_details','Contact options',['eyebrow'=>'GET IN TOUCH','title'=>'HOW CAN WE HELP?']],
                ['contact_form','Contact form',['eyebrow'=>'SEND US A MESSAGE','title'=>'GET IN TOUCH','subtitle'=>'Fill in the form and the team will get back to you.','button_label'=>'Send Message']],
                ['map_embed','Location',['eyebrow'=>'OUR LOCATION','title'=>'VISIT KELIKA','body'=>'The official facility address and map pin must be confirmed before launch.']],
                ['faq','Quick answers',['eyebrow'=>'FREQUENTLY ASKED QUESTIONS','title'=>'QUICK ANSWERS','items'=>[['question'=>'How can I ask about court booking?','answer'=>'Use the contact form or the configured booking link when it is available.'],['question'=>'What age groups do you offer?','answer'=>'Contact the team to confirm current program age groups and availability.'],['question'=>'Do you conduct tournaments and events?','answer'=>'Published event listings will show confirmed events and dates.'],['question'=>'Can I visit the facility before joining?','answer'=>'Contact the team to confirm visiting arrangements.']]]],
                ['cta','Contact CTA',['eyebrow'=>'STILL HAVE QUESTIONS?','title'=>'WE’RE HERE TO HELP','subtitle'=>'Reach out and we will guide you to the right information.','button_label'=>'Contact Us','button_url'=>'#contact-form','secondary_button_label'=>'Book a Court','secondary_button_url'=>'','tone'=>'green']],
            ]],
        ];

        foreach ($pages as $slug=>$pageData) {
            [$title,$sections]=$pageData;
            $page=Page::query()->firstOrCreate(['slug'=>$slug],['title'=>$title,'template'=>'standard','status'=>'published','published_at'=>now(),'show_in_menu'=>true,'sort_order'=>array_search($slug,array_keys($pages),true)+1,'robots_directive'=>'index,follow','seo_title'=>$title.' | KELIKA']);
            if (!$page->sections()->exists()) {
                foreach($sections as $index=>[$type,$label,$data]) $page->sections()->create(['type'=>$type,'admin_label'=>'DRAFT — '.$label,'sort_order'=>$index+1,'is_visible'=>true,'data'=>$data]);
            }
        }
    }

    private function seedDraftContent(): void
    {
        $content = [
            'sport'=>[
                ['Badminton',['image'=>'https://images.unsplash.com/photo-1780233689566-55045608bd17?auto=format&fit=crop&w=1200&q=82','image_alt'=>'Badminton players practicing on an indoor court','eyebrow'=>'FEATURED SPORT','tagline'=>'SKILL. SPEED. STRATEGY.','description'=>'Badminton can help players build agility, fitness, confidence and game understanding. Confirm the final program details and availability before publishing.','features'=>[['title'=>'For All Ages','description'=>'Confirm age groups and entry levels.','icon'=>'♧'],['title'=>'Expert Coaching','description'=>'Add verified coaching information.','icon'=>'◎'],['title'=>'Training Programs','description'=>'Add available training pathways.','icon'=>'▦'],['title'=>'Tournaments','description'=>'List confirmed events only.','icon'=>'♜']]]],
                ['Football',['image'=>'https://images.unsplash.com/photo-1517466787929-bc90951d0974?auto=format&fit=crop&w=1200&q=82','image_alt'=>'Football players training on a field','eyebrow'=>'OUR SPORT','tagline'=>'TEAMWORK. FITNESS. GROWTH.','description'=>'Football training may focus on skill development, fitness, tactical understanding and teamwork. Confirm program availability before publishing.','features'=>[['title'=>'Skill Development','description'=>'Technique and game understanding.','icon'=>'↗'],['title'=>'Fitness & Conditioning','description'=>'Speed, agility and endurance.','icon'=>'↗'],['title'=>'Team Training','description'=>'Only if team sessions are offered.','icon'=>'♧'],['title'=>'Match Exposure','description'=>'Confirm match opportunities.','icon'=>'♜']]]],
                ['Cricket',['image'=>'https://images.unsplash.com/photo-1531415074968-036ba1b575da?auto=format&fit=crop&w=1200&q=82','image_alt'=>'Cricket equipment and field','eyebrow'=>'OUR SPORT','tagline'=>'FOCUS. TECHNIQUE. PERFORMANCE.','description'=>'Cricket training may cover core skills, technique, match awareness and fitness. Confirm program availability before publishing.','features'=>[['title'=>'Batting & Bowling','description'=>'Confirm coaching coverage.','icon'=>'↗'],['title'=>'Game Strategy','description'=>'Tactical understanding.','icon'=>'◎'],['title'=>'Fitness Training','description'=>'Strength and agility.','icon'=>'↗'],['title'=>'Match Practice','description'=>'Confirm available sessions.','icon'=>'♜']]]],
            ],
            'program'=>[
                ['Kids Program',['image'=>'https://images.unsplash.com/photo-1779608103749-395f76f42f94?auto=format&fit=crop&w=1000&q=80','image_alt'=>'Child taking part in a badminton session','age_range'=>'Ages 5–12','description'=>'Confirm age range, program schedule and availability before publishing.']],
                ['Youth Development',['image'=>'https://images.unsplash.com/photo-1774652995555-59fa40d9306c?auto=format&fit=crop&w=1000&q=80','image_alt'=>'Young athlete practicing badminton','age_range'=>'Ages 13–18','description'=>'Confirm age range, program schedule and availability before publishing.']],
                ['Performance Training',['image'=>'https://images.unsplash.com/photo-1722087642932-9b070e9a066e?auto=format&fit=crop&w=1000&q=80','image_alt'=>'Badminton player training for performance','description'=>'Confirm eligibility, coaching structure and availability before publishing.']],
                ['Adult Coaching',['image'=>'https://images.unsplash.com/photo-1774652995555-59fa40d9306c?auto=format&fit=crop&w=1000&q=80','image_alt'=>'Adult badminton player training','age_range'=>'18+','description'=>'Confirm age range, program schedule and availability before publishing.']],
            ],
            'facility'=>[
                ['Professional Badminton Courts',['image'=>'https://images.unsplash.com/photo-1775993167284-8e6a6e56ab69?auto=format&fit=crop&w=1200&q=82','image_alt'=>'Indoor badminton courts with blue flooring','description'=>'Confirm court count, flooring, lighting, and operating availability before publishing.']],
                ['Player Lounge',['image'=>'https://images.unsplash.com/photo-1770085057829-97e7a45e1916?auto=format&fit=crop&w=1200&q=82','image_alt'=>'Modern sports facility interior','description'=>'Confirm whether this amenity is available at the facility.']],
                ['Changing Rooms',['image'=>'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=1200&q=82','image_alt'=>'Clean modern changing and washroom facilities','description'=>'Confirm amenity availability before publishing.']],
            ],
        ];
        foreach($content as $type=>$items) foreach($items as $index=>[$title,$data]) ContentItem::query()->firstOrCreate(['type'=>$type,'slug'=>Str::slug($title)],['title'=>$title,'status'=>'published','sort_order'=>$index+1,'data'=>$data]);
    }
}
