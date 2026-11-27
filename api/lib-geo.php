<?php
// Finds where an order goes from its written address: one of Bangladesh's 64 districts and, inside Dhaka,
// the area (Mirpur, Uttara, Jurain …). Used by the admin Order Map. Map points are approximate centres.

// key => [name, Bangla name, lat, lng, division, names to look for in addresses]
const IC_BD_DISTRICTS = [
    'dhaka' => ['Dhaka', 'ঢাকা', 23.8103, 90.4125, 'Dhaka', ['dhaka', 'ঢাকা']],
    'gazipur' => ['Gazipur', 'গাজীপুর', 23.9999, 90.4203, 'Dhaka', ['gazipur', 'tongi', 'গাজীপুর', 'টঙ্গী']],
    'narayanganj' => ['Narayanganj', 'নারায়ণগঞ্জ', 23.6238, 90.5000, 'Dhaka', ['narayanganj', 'narayangonj', 'নারায়ণগঞ্জ', 'নারায়নগঞ্জ']],
    'narsingdi' => ['Narsingdi', 'নরসিংদী', 23.9322, 90.7154, 'Dhaka', ['narsingdi', 'narshingdi', 'নরসিংদী']],
    'munshiganj' => ['Munshiganj', 'মুন্সীগঞ্জ', 23.5422, 90.5305, 'Dhaka', ['munshiganj', 'মুন্সীগঞ্জ', 'মুন্সিগঞ্জ']],
    'manikganj' => ['Manikganj', 'মানিকগঞ্জ', 23.8644, 90.0047, 'Dhaka', ['manikganj', 'মানিকগঞ্জ']],
    'tangail' => ['Tangail', 'টাঙ্গাইল', 24.2513, 89.9167, 'Dhaka', ['tangail', 'টাঙ্গাইল']],
    'kishoreganj' => ['Kishoreganj', 'কিশোরগঞ্জ', 24.4449, 90.7766, 'Dhaka', ['kishoreganj', 'kishorganj', 'কিশোরগঞ্জ']],
    'faridpur' => ['Faridpur', 'ফরিদপুর', 23.6070, 89.8429, 'Dhaka', ['faridpur', 'ফরিদপুর']],
    'gopalganj' => ['Gopalganj', 'গোপালগঞ্জ', 23.0050, 89.8266, 'Dhaka', ['gopalganj', 'গোপালগঞ্জ']],
    'madaripur' => ['Madaripur', 'মাদারীপুর', 23.1641, 90.1897, 'Dhaka', ['madaripur', 'মাদারীপুর']],
    'rajbari' => ['Rajbari', 'রাজবাড়ী', 23.7574, 89.6444, 'Dhaka', ['rajbari', 'রাজবাড়ী', 'রাজবাড়ি']],
    'shariatpur' => ['Shariatpur', 'শরীয়তপুর', 23.2423, 90.4348, 'Dhaka', ['shariatpur', 'শরীয়তপুর']],
    'chattogram' => ['Chattogram', 'চট্টগ্রাম', 22.3569, 91.7832, 'Chattogram', ['chattogram', 'chittagong', 'ctg', 'চট্টগ্রাম']],
    'coxsbazar' => ["Cox's Bazar", 'কক্সবাজার', 21.4272, 92.0058, 'Chattogram', ['coxs bazar', 'cox bazar', 'coxsbazar', 'কক্সবাজার']],
    'cumilla' => ['Cumilla', 'কুমিল্লা', 23.4607, 91.1809, 'Chattogram', ['cumilla', 'comilla', 'কুমিল্লা']],
    'feni' => ['Feni', 'ফেনী', 23.0159, 91.3976, 'Chattogram', ['feni', 'ফেনী']],
    'noakhali' => ['Noakhali', 'নোয়াখালী', 22.8696, 91.0995, 'Chattogram', ['noakhali', 'নোয়াখালী', 'নোয়াখালি']],
    'lakshmipur' => ['Lakshmipur', 'লক্ষ্মীপুর', 22.9447, 90.8282, 'Chattogram', ['lakshmipur', 'laxmipur', 'lakshipur', 'লক্ষ্মীপুর']],
    'chandpur' => ['Chandpur', 'চাঁদপুর', 23.2333, 90.6713, 'Chattogram', ['chandpur', 'চাঁদপুর']],
    'brahmanbaria' => ['Brahmanbaria', 'ব্রাহ্মণবাড়িয়া', 23.9571, 91.1119, 'Chattogram', ['brahmanbaria', 'brahamanbaria', 'b baria', 'ব্রাহ্মণবাড়িয়া']],
    'rangamati' => ['Rangamati', 'রাঙ্গামাটি', 22.6533, 92.1789, 'Chattogram', ['rangamati', 'রাঙ্গামাটি', 'রাঙামাটি']],
    'khagrachhari' => ['Khagrachhari', 'খাগড়াছড়ি', 23.1193, 91.9847, 'Chattogram', ['khagrachhari', 'khagrachari', 'খাগড়াছড়ি']],
    'bandarban' => ['Bandarban', 'বান্দরবান', 22.1953, 92.2184, 'Chattogram', ['bandarban', 'বান্দরবান']],
    'rajshahi' => ['Rajshahi', 'রাজশাহী', 24.3745, 88.6042, 'Rajshahi', ['rajshahi', 'রাজশাহী']],
    'bogura' => ['Bogura', 'বগুড়া', 24.8465, 89.3773, 'Rajshahi', ['bogura', 'bogra', 'বগুড়া']],
    'pabna' => ['Pabna', 'পাবনা', 24.0064, 89.2372, 'Rajshahi', ['pabna', 'পাবনা']],
    'sirajganj' => ['Sirajganj', 'সিরাজগঞ্জ', 24.4534, 89.7007, 'Rajshahi', ['sirajganj', 'sirajgonj', 'সিরাজগঞ্জ']],
    'natore' => ['Natore', 'নাটোর', 24.4206, 89.0003, 'Rajshahi', ['natore', 'নাটোর']],
    'naogaon' => ['Naogaon', 'নওগাঁ', 24.7936, 88.9318, 'Rajshahi', ['naogaon', 'নওগাঁ']],
    'chapainawabganj' => ['Chapainawabganj', 'চাঁপাইনবাবগঞ্জ', 24.5965, 88.2776, 'Rajshahi', ['chapainawabganj', 'chapai nawabganj', 'chapai', 'চাঁপাইনবাবগঞ্জ', 'চাঁপাই']],
    'joypurhat' => ['Joypurhat', 'জয়পুরহাট', 25.0968, 89.0227, 'Rajshahi', ['joypurhat', 'jaipurhat', 'জয়পুরহাট']],
    'khulna' => ['Khulna', 'খুলনা', 22.8456, 89.5403, 'Khulna', ['khulna', 'খুলনা']],
    'jashore' => ['Jashore', 'যশোর', 23.1664, 89.2081, 'Khulna', ['jashore', 'jessore', 'যশোর']],
    'satkhira' => ['Satkhira', 'সাতক্ষীরা', 22.7185, 89.0705, 'Khulna', ['satkhira', 'সাতক্ষীরা']],
    'bagerhat' => ['Bagerhat', 'বাগেরহাট', 22.6516, 89.7859, 'Khulna', ['bagerhat', 'বাগেরহাট']],
    'kushtia' => ['Kushtia', 'কুষ্টিয়া', 23.9013, 89.1204, 'Khulna', ['kushtia', 'কুষ্টিয়া']],
    'jhenaidah' => ['Jhenaidah', 'ঝিনাইদহ', 23.5450, 89.1726, 'Khulna', ['jhenaidah', 'jhenaidaha', 'ঝিনাইদহ']],
    'magura' => ['Magura', 'মাগুরা', 23.4873, 89.4199, 'Khulna', ['magura', 'মাগুরা']],
    'narail' => ['Narail', 'নড়াইল', 23.1725, 89.5126, 'Khulna', ['narail', 'নড়াইল']],
    'chuadanga' => ['Chuadanga', 'চুয়াডাঙ্গা', 23.6402, 88.8418, 'Khulna', ['chuadanga', 'চুয়াডাঙ্গা']],
    'meherpur' => ['Meherpur', 'মেহেরপুর', 23.7622, 88.6318, 'Khulna', ['meherpur', 'মেহেরপুর']],
    'barishal' => ['Barishal', 'বরিশাল', 22.7010, 90.3535, 'Barishal', ['barishal', 'barisal', 'বরিশাল']],
    'patuakhali' => ['Patuakhali', 'পটুয়াখালী', 22.3596, 90.3299, 'Barishal', ['patuakhali', 'পটুয়াখালী']],
    'bhola' => ['Bhola', 'ভোলা', 22.6859, 90.6482, 'Barishal', ['bhola', 'ভোলা']],
    'pirojpur' => ['Pirojpur', 'পিরোজপুর', 22.5841, 89.9720, 'Barishal', ['pirojpur', 'পিরোজপুর']],
    'jhalokati' => ['Jhalokati', 'ঝালকাঠি', 22.6406, 90.1987, 'Barishal', ['jhalokati', 'jhalakathi', 'jhalokathi', 'ঝালকাঠি']],
    'barguna' => ['Barguna', 'বরগুনা', 22.1591, 90.1119, 'Barishal', ['barguna', 'বরগুনা']],
    'sylhet' => ['Sylhet', 'সিলেট', 24.8949, 91.8687, 'Sylhet', ['sylhet', 'সিলেট']],
    'moulvibazar' => ['Moulvibazar', 'মৌলভীবাজার', 24.4829, 91.7774, 'Sylhet', ['moulvibazar', 'maulvibazar', 'sreemangal', 'srimangal', 'মৌলভীবাজার', 'শ্রীমঙ্গল']],
    'habiganj' => ['Habiganj', 'হবিগঞ্জ', 24.3745, 91.4155, 'Sylhet', ['habiganj', 'হবিগঞ্জ']],
    'sunamganj' => ['Sunamganj', 'সুনামগঞ্জ', 25.0658, 91.3950, 'Sylhet', ['sunamganj', 'সুনামগঞ্জ']],
    'rangpur' => ['Rangpur', 'রংপুর', 25.7439, 89.2752, 'Rangpur', ['rangpur', 'রংপুর']],
    'dinajpur' => ['Dinajpur', 'দিনাজপুর', 25.6217, 88.6354, 'Rangpur', ['dinajpur', 'দিনাজপুর']],
    'kurigram' => ['Kurigram', 'কুড়িগ্রাম', 25.8054, 89.6362, 'Rangpur', ['kurigram', 'কুড়িগ্রাম']],
    'gaibandha' => ['Gaibandha', 'গাইবান্ধা', 25.3288, 89.5430, 'Rangpur', ['gaibandha', 'গাইবান্ধা']],
    'nilphamari' => ['Nilphamari', 'নীলফামারী', 25.9310, 88.8560, 'Rangpur', ['nilphamari', 'saidpur', 'নীলফামারী', 'সৈয়দপুর']],
    'lalmonirhat' => ['Lalmonirhat', 'লালমনিরহাট', 25.9923, 89.2847, 'Rangpur', ['lalmonirhat', 'লালমনিরহাট']],
    'thakurgaon' => ['Thakurgaon', 'ঠাকুরগাঁও', 26.0337, 88.4617, 'Rangpur', ['thakurgaon', 'ঠাকুরগাঁও']],
    'panchagarh' => ['Panchagarh', 'পঞ্চগড়', 26.3411, 88.5542, 'Rangpur', ['panchagarh', 'পঞ্চগড়']],
    'mymensingh' => ['Mymensingh', 'ময়মনসিংহ', 24.7471, 90.4203, 'Mymensingh', ['mymensingh', 'ময়মনসিংহ']],
    'jamalpur' => ['Jamalpur', 'জামালপুর', 24.9375, 89.9378, 'Mymensingh', ['jamalpur', 'জামালপুর']],
    'netrokona' => ['Netrokona', 'নেত্রকোনা', 24.8709, 90.7279, 'Mymensingh', ['netrokona', 'netrakona', 'নেত্রকোনা']],
    'sherpur' => ['Sherpur', 'শেরপুর', 25.0205, 90.0153, 'Mymensingh', ['sherpur', 'শেরপুর']],
];

// Areas inside Dhaka district, grouped to match the official thana shapes on the map (assets/geo/dhaka-thanas.geojson):
// key => [name, Bangla, lat, lng, names to look for]
const IC_DHAKA_AREAS = [
    'mirpur' => ['Mirpur / Pallabi', 'মিরপুর', 23.8223, 90.3654, ['mirpur', 'pallabi', 'kafrul', 'darus salam', 'darussalam', 'shah ali', 'মিরপুর', 'পল্লবী', 'কাফরুল', 'দারুস সালাম']],
    'uttara' => ['Uttara', 'উত্তরা', 23.8759, 90.3795, ['uttara', 'উত্তরা']],
    'turag' => ['Turag / Diabari', 'তুরাগ', 23.8800, 90.3500, ['turag', 'diabari', 'তুরাগ', 'দিয়াবাড়ি']],
    'airport' => ['Airport / Biman Bandar', 'বিমানবন্দর', 23.8513, 90.4080, ['airport', 'biman bandar', 'বিমানবন্দর', 'এয়ারপোর্ট']],
    'dhanmondi' => ['Dhanmondi', 'ধানমন্ডি', 23.7465, 90.3760, ['dhanmondi', 'ধানমন্ডি', 'ধানমণ্ডি']],
    'kalabagan' => ['Kalabagan / Panthapath', 'কলাবাগান', 23.7480, 90.3820, ['kalabagan', 'panthapath', 'কলাবাগান', 'পান্থপথ']],
    'newmarket' => ['New Market / Azimpur', 'নিউ মার্কেট', 23.7330, 90.3850, ['new market', 'newmarket', 'azimpur', 'nilkhet', 'নিউ মার্কেট', 'আজিমপুর', 'নীলক্ষেত']],
    'mohammadpur' => ['Mohammadpur', 'মোহাম্মদপুর', 23.7662, 90.3589, ['mohammadpur', 'মোহাম্মদপুর']],
    'shyamoli' => ['Shyamoli / Adabor', 'শ্যামলী', 23.7746, 90.3653, ['shyamoli', 'adabor', 'শ্যামলী', 'আদাবর']],
    'agargaon' => ['Agargaon / Sher-e-Bangla Nagar', 'আগারগাঁও', 23.7780, 90.3780, ['agargaon', 'sher e bangla nagar', 'আগারগাঁও', 'শেরেবাংলা নগর']],
    'gulshan' => ['Gulshan / Banani / Mohakhali', 'গুলশান', 23.7925, 90.4100, ['gulshan', 'banani', 'mohakhali', 'গুলশান', 'বনানী', 'মহাখালী']],
    'badda' => ['Badda / Bashundhara', 'বাড্ডা', 23.7900, 90.4300, ['badda', 'bashundhara', 'basundhara', 'vatara', 'বাড্ডা', 'বসুন্ধরা', 'ভাটারা']],
    'khilkhet' => ['Khilkhet / Nikunja', 'খিলক্ষেত', 23.8310, 90.4243, ['khilkhet', 'nikunja', 'খিলক্ষেত', 'নিকুঞ্জ']],
    'dakshinkhan' => ['Dakshinkhan / Uttarkhan', 'দক্ষিণখান', 23.8600, 90.4250, ['dakshinkhan', 'uttarkhan', 'uttar khan', 'দক্ষিণখান', 'উত্তরখান']],
    'cantonment' => ['Cantonment', 'ক্যান্টনমেন্ট', 23.8250, 90.4000, ['cantonment', 'ক্যান্টনমেন্ট']],
    'tejgaon' => ['Tejgaon / Farmgate', 'তেজগাঁও', 23.7600, 90.3900, ['tejgaon', 'farmgate', 'তেজগাঁও', 'ফার্মগেট']],
    'rampura' => ['Rampura / Banasree', 'রামপুরা', 23.7612, 90.4209, ['rampura', 'banasree', 'banashree', 'রামপুরা', 'বনশ্রী']],
    'khilgaon' => ['Khilgaon', 'খিলগাঁও', 23.7516, 90.4253, ['khilgaon', 'খিলগাঁও']],
    'ramna' => ['Ramna / Moghbazar / Shahbag', 'রমনা', 23.7450, 90.4000, ['ramna', 'shahbag', 'shahbagh', 'segunbagicha', 'moghbazar', 'mogbazar', 'malibagh', 'eskaton', 'রমনা', 'শাহবাগ', 'মগবাজার', 'মালিবাগ', 'ইস্কাটন']],
    'paltan' => ['Paltan / Kakrail', 'পল্টন', 23.7360, 90.4120, ['paltan', 'kakrail', 'পল্টন', 'কাকরাইল']],
    'motijheel' => ['Motijheel', 'মতিঝিল', 23.7330, 90.4172, ['motijheel', 'মতিঝিল']],
    'basabo' => ['Basabo / Sabujbagh', 'বাসাবো', 23.7397, 90.4290, ['basabo', 'sabujbagh', 'বাসাবো', 'সবুজবাগ']],
    'hazaribagh' => ['Hazaribagh', 'হাজারীবাগ', 23.7350, 90.3600, ['hazaribagh', 'হাজারীবাগ']],
    'kamrangirchar' => ['Kamrangirchar', 'কামরাঙ্গীরচর', 23.7200, 90.3700, ['kamrangirchar', 'kamrangir char', 'কামরাঙ্গীরচর']],
    'olddhaka' => ['Old Dhaka', 'পুরান ঢাকা', 23.7150, 90.4000, ['lalbagh', 'chawkbazar', 'chowkbazar', 'chak bazar', 'sutrapur', 'kotwali', 'bangshal', 'old dhaka', 'puran dhaka', 'লালবাগ', 'চকবাজার', 'সূত্রাপুর', 'কোতোয়ালী', 'বংশাল', 'পুরান ঢাকা']],
    'wari' => ['Wari / Gendaria', 'ওয়ারী', 23.7130, 90.4210, ['wari', 'gendaria', 'ওয়ারী', 'গেন্ডারিয়া']],
    'jatrabari' => ['Jatrabari', 'যাত্রাবাড়ী', 23.7104, 90.4349, ['jatrabari', 'যাত্রাবাড়ী', 'যাত্রাবাড়ি']],
    'jurain' => ['Jurain / Shyampur', 'জুরাইন', 23.6928, 90.4310, ['jurain', 'jurine', 'shyampur', 'kadamtali', 'postogola', 'জুরাইন', 'শ্যামপুর', 'কদমতলী']],
    'demra' => ['Demra', 'ডেমরা', 23.7231, 90.4846, ['demra', 'ডেমরা']],
    'keraniganj' => ['Keraniganj', 'কেরানীগঞ্জ', 23.6980, 90.3450, ['keraniganj', 'কেরানীগঞ্জ']],
    'savar' => ['Savar / Ashulia', 'সাভার', 23.8583, 90.2667, ['savar', 'ashulia', 'সাভার', 'আশুলিয়া']],
    'dhamrai' => ['Dhamrai', 'ধামরাই', 23.9150, 90.2150, ['dhamrai', 'ধামরাই']],
    'dohar' => ['Dohar', 'দোহার', 23.5900, 90.1400, ['dohar', 'দোহার']],
];

// Lower-cases and simplifies an address so the names above can be found in it
function ic_geo_normalize(string $address): string
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($address, 'UTF-8') : strtolower($address);
    $text = str_replace(["'", '’', '`'], '', $text);
    return ' ' . preg_replace('/[\s,.;:\/\\\\()\-–—#|]+/u', ' ', $text) . ' ';
}

// Position (bytes) of the LAST mention of any of the names, or -1. English names must be whole words.
function ic_geo_last_mention(string $text, array $names): int
{
    $best = -1;
    foreach ($names as $name) {
        if (preg_match('/^[a-z ]+$/', $name)) {
            if (preg_match_all('/(?<![a-z])' . preg_quote($name, '/') . '(?![a-z])/', $text, $m, PREG_OFFSET_CAPTURE)) {
                $best = max($best, (int) end($m[0])[1]);
            }
        } else {
            $pos = strrpos($text, $name);
            if ($pos !== false) {
                $best = max($best, $pos);
            }
        }
    }
    return $best;
}

// Address → ['district' => key, 'area' => Dhaka area key or null, 'guessed' => true when only the delivery
// zone was known] or null when the place can't be told. Addresses usually end with the district, so the
// name written last wins ("Mirpur, Kushtia" is Kushtia).
function ic_geo_locate(string $address, ?string $region): ?array
{
    $text = ic_geo_normalize($address);
    $place = null;
    $pos = -1;
    foreach (IC_BD_DISTRICTS as $key => $d) {
        $p = ic_geo_last_mention($text, $d[5]);
        if ($p > $pos) {
            [$pos, $place] = [$p, ['district' => $key, 'area' => null]];
        }
    }
    $area = null;
    $areaPos = -1;
    foreach (IC_DHAKA_AREAS as $key => $a) {
        $p = ic_geo_last_mention($text, $a[4]);
        if ($p > $areaPos) {
            [$areaPos, $area] = [$p, $key];
        }
    }
    if ($area !== null && ($place === null || $place['district'] === 'dhaka' || $areaPos > $pos)) {
        return ['district' => 'dhaka', 'area' => $area, 'guessed' => false];
    }
    if ($place !== null) {
        return $place + ['guessed' => false];
    }
    if ($region === 'dhaka') {
        return ['district' => 'dhaka', 'area' => null, 'guessed' => true];
    }
    return null;
}
