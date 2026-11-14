<?php
declare(strict_types=1);

/** Demo data. Every person, guest, vendor and event is fictional. */
final class Seed {
    private const FIRST = ['Avery', 'Jordan', 'Morgan', 'Riley', 'Casey', 'Quinn', 'Taylor', 'Jamie', 'Parker', 'Rowan', 'Elliot', 'Harper', 'Sasha', 'Robin', 'Devon', 'Kendall', 'Logan', 'Reese', 'Skyler', 'Emerson', 'Noor', 'Mei', 'Arjun', 'Lucía', 'Tomas', 'Amara', 'Kai', 'Ingrid', 'Mateo', 'Priya', 'Oskar', 'Leilani', 'Samir', 'Hana', 'Wren', 'Nico'];
    private const LAST = ['Beaulieu', 'Carter', 'Dumont', 'Fraser', 'Gauthier', 'Hughes', 'Iverson', 'Johal', 'Kowalski', 'Lachance', 'MacLeod', 'Nguyen', 'Okafor', 'Petrov', 'Quellette', 'Rasmussen', 'Singh', 'Tremblay', 'Underhill', 'Vasquez', 'Whitford', 'Yamada', 'Zhou', 'Bergeron', 'Chartrand', 'Delorme', 'Ellison', 'Fontaine', 'Gill', 'Halvorsen'];

    private static function name(int $i): string { return self::FIRST[$i % count(self::FIRST)] . ' ' . self::LAST[($i * 7 + 3) % count(self::LAST)]; }
    private static function ago(int $minutes): string { return U::ts(time() - $minutes * 60); }

    public static function run(): array {
        mt_srand(2026);
        $now = U::now();
        $ins = fn(string $t, array $r) => Db::insert($t, $r);

        // ---------- departments ----------
        $D = [];
        foreach ([
            ['FO', 'Front Office', '🛎️', '#1D5C63', 'Front desk, guest services, concierge, night audit', '100'],
            ['HK', 'Housekeeping', '🧹', '#4E9AA0', 'Guest rooms, public areas, turndown, lost and found', '200'],
            ['ENG', 'Engineering', '🔧', '#F0A23B', 'Maintenance, mechanical, life safety, grounds, snow', '300'],
            ['FB', 'Food & Beverage', '🍽️', '#B5532F', 'Restaurant, lounge, in-room dining, minibar', '400'],
            ['KIT', 'Kitchen', '👨‍🍳', '#8A3B12', 'Culinary production, HACCP, stewarding', '410'],
            ['BQ', 'Banquets & Events', '🎉', '#6B4FA0', 'Meeting rooms, weddings, conferences, set-ups', '450'],
            ['SEC', 'Security', '🛡️', '#2A2F36', 'Loss prevention, patrols, incidents, key control', '500'],
            ['IT', 'IT & Technology', '💻', '#2F6F9F', 'Wi-Fi, TV, phones, POS, Opera workstations, AV', '550'],
            ['LND', 'Laundry', '🧺', '#7FA4A8', 'Linen, uniforms, guest laundry and dry cleaning', '250'],
            ['SALES', 'Sales & Catering', '📈', '#3E8E7E', 'Group sales, catering sales, BEOs', '600'],
            ['ADM', 'Administration', '🏢', '#5B6770', 'General manager, HR, finance, purchasing', '700'],
        ] as $i => [$code, $name, $icon, $col, $desc, $ext]) {
            $D[$code] = $ins('departments', ['code' => $code, 'name' => $name, 'icon' => $icon, 'colour' => $col, 'description' => $desc, 'phone_ext' => $ext, 'email' => strtolower($code) . '@example.com', 'sort' => $i, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // ---------- users ----------
        $demoPass = 'Demo-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $adminPass = U::token(12);
        $demoHash = Auth::hash($demoPass);
        $U = [];
        $mk = function (string $username, string $name, string $role, ?string $dept, string $title, array $extra = []) use (&$U, $ins, $now, $demoHash, $D) {
            $U[$username] = $ins('users', ['username' => $username, 'name' => $name, 'email' => "$username@example.com", 'role' => $role, 'department_id' => $dept ? $D[$dept] : null, 'title' => $title,
                'password_hash' => $demoHash, 'active' => 1, 'on_shift' => 1, 'notify_email' => 1, 'notify_push' => 1, 'password_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now] + $extra);
            return $U[$username];
        };
        $mk('admin', 'ExpTix Administrator', 'admin', 'ADM', 'System administrator');
        Db::q('UPDATE users SET password_hash = ? WHERE username = ?', [Auth::hash($adminPass), 'admin']);
        $mk('gm', 'Avery Fontaine', 'gm', 'ADM', 'General Manager', ['extra_departments' => json_encode(array_values($D))]);
        $mk('fo.manager', 'Jordan Tremblay', 'manager', 'FO', 'Front Office Manager');
        $mk('hk.manager', 'Morgan Nguyen', 'manager', 'HK', 'Executive Housekeeper', ['extra_departments' => json_encode([$D['LND']])]);
        $mk('chief.eng', 'Riley MacLeod', 'manager', 'ENG', 'Chief Engineer');
        $mk('fb.manager', 'Casey Okafor', 'manager', 'FB', 'Food & Beverage Manager', ['extra_departments' => json_encode([$D['KIT'], $D['BQ']])]);
        $mk('chef', 'Quinn Lachance', 'manager', 'KIT', 'Executive Chef');
        $mk('events', 'Taylor Singh', 'manager', 'BQ', 'Events Manager', ['extra_departments' => json_encode([$D['SALES']])]);
        $mk('security.lead', 'Jamie Petrov', 'supervisor', 'SEC', 'Security Supervisor');
        $mk('it.lead', 'Parker Johal', 'supervisor', 'IT', 'IT Coordinator');
        $mk('hk.super', 'Rowan Gauthier', 'supervisor', 'HK', 'Housekeeping Supervisor');
        $mk('eng.super', 'Elliot Hughes', 'supervisor', 'ENG', 'Assistant Chief Engineer');
        $mk('laundry.lead', 'Harper Iverson', 'supervisor', 'LND', 'Laundry Supervisor');
        foreach (['Sasha Beaulieu', 'Robin Dumont', 'Devon Zhou', 'Kendall Vasquez'] as $i => $n) $mk('fd' . ($i + 1), $n, 'frontdesk', 'FO', $i === 3 ? 'Night Auditor' : 'Guest Service Agent');
        $hkNames = ['Noor Rasmussen', 'Mei Kowalski', 'Arjun Fraser', 'Lucía Bergeron', 'Tomas Ellison', 'Amara Delorme', 'Ingrid Carter', 'Mateo Whitford'];
        foreach ($hkNames as $i => $n) $mk('hk' . ($i + 1), $n, 'staff', 'HK', 'Room Attendant', ['on_shift' => $i < 6 ? 1 : 0, 'hk_credit_capacity' => 14]);
        $mk('houseperson', 'Kai Halvorsen', 'staff', 'HK', 'Houseperson');
        foreach (['Priya Underhill', 'Oskar Gill', 'Samir Yamada'] as $i => $n) $mk('eng' . ($i + 1), $n, 'staff', 'ENG', $i === 2 ? 'Maintenance Technician (nights)' : 'Maintenance Technician', ['on_shift' => $i < 2 ? 1 : 0]);
        foreach (['Leilani Chartrand', 'Hana Quellette', 'Wren Fontaine'] as $i => $n) $mk('server' . ($i + 1), $n, 'staff', 'FB', $i === 2 ? 'Bartender' : 'Server');
        foreach (['Nico Beaulieu', 'Logan Petrov'] as $i => $n) $mk('cook' . ($i + 1), $n, 'staff', 'KIT', $i ? 'Line Cook' : 'Sous Chef');
        $mk('steward', 'Reese Hughes', 'staff', 'KIT', 'Steward');
        $mk('banquet1', 'Skyler Dumont', 'staff', 'BQ', 'Banquet Captain');
        $mk('security1', 'Emerson Gill', 'staff', 'SEC', 'Security Officer');
        $mk('laundry1', 'Jordan Yamada', 'staff', 'LND', 'Laundry Attendant');
        $mk('auditor', 'Compliance Reviewer', 'auditor', null, 'Read-only auditor');
        $mk('contractor', 'Summit Elevator (demo)', 'contractor', 'ENG', 'Elevator contractor');
        Db::q('UPDATE departments SET manager_id = ? WHERE code = ?', [$U['fo.manager'], 'FO']);
        foreach (['HK' => 'hk.manager', 'ENG' => 'chief.eng', 'FB' => 'fb.manager', 'KIT' => 'chef', 'BQ' => 'events', 'SEC' => 'security.lead', 'IT' => 'it.lead', 'LND' => 'hk.manager', 'SALES' => 'events', 'ADM' => 'gm'] as $c => $u) Db::q('UPDATE departments SET manager_id = ? WHERE code = ?', [$U[$u], $c]);

        // ---------- SLA ----------
        $S = [];
        foreach ([['Critical: life safety / outage', 'critical', 5, 60, 30, 'gm'], ['Urgent', 'urgent', 10, 120, 60, 'gm'], ['High', 'high', 15, 240, 60, 'manager'], ['Normal', 'normal', 30, 480, 120, 'manager'], ['Low', 'low', 120, 2880, 480, 'manager'],
            ['Guest amenity (20 min promise)', null, 5, 20, 15, 'manager'], ['Guest comfort (in-room)', null, 10, 60, 30, 'manager'], ['In-room dining (35 min promise)', null, 5, 35, 15, 'manager'], ['Guest complaint', null, 10, 60, 30, 'gm']] as [$n, $p, $r, $res, $esc, $role]) {
            $S[$n] = $ins('sla_policies', ['name' => $n, 'priority' => $p, 'response_minutes' => $r, 'resolve_minutes' => $res, 'escalate_after_minutes' => $esc, 'escalate_role' => $role, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // ---------- checklist templates ----------
        $T = [];
        $tpl = function (string $name, string $dept, string $kind, array $items, int $pass = 80) use (&$T, $ins, $D, $now) {
            $T[$name] = $ins('checklist_templates', ['name' => $name, 'department_id' => $D[$dept], 'kind' => $kind, 'items' => implode("\n", $items), 'pass_mark' => $pass, 'create_tickets_on_fail' => 1, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        };
        $tpl('Guest room inspection', 'HK', 'room_inspection', ['## Entry', 'Door closes and latches, lock and deadbolt work', 'Door viewer, safety chain, fire evacuation map in place', '## Bedroom', 'Bed made to standard, no stains or hair', 'Under bed and behind headboard clean', 'Furniture dusted, no damage', 'Drapes and blackout close fully', 'Thermostat set to 21 °C, heat responds', 'All lamps and switches work', 'TV, remote and channel guide work', 'Phone has dial tone, info card present', 'Wi-Fi card present', 'Minibar / fridge stocked and cold', '## Bathroom', 'Toilet, tub and shower clean, no hair', 'No mould or grout staining', 'Hot water reaches temperature within 30 seconds', 'Drains flow freely', 'Amenities complete: shampoo, conditioner, lotion, soap', 'Towels: 2 bath, 2 hand, 2 face, 1 mat', 'Hair dryer works', '## Final', 'Room smells fresh', 'Window and sill clean, no drafts', 'Smoke detector light flashing green'], 90);
        $tpl('Departure clean (attendant)', 'HK', 'inspection', ['Strip beds and remove linen', 'Remove trash and recycling', 'Check for lost and found items', 'Clean and sanitise bathroom', 'Make beds with fresh linen', 'Dust all surfaces', 'Vacuum including under furniture', 'Restock amenities and coffee', 'Check all lights and TV', 'Set thermostat and close drapes']);
        $tpl('Public area inspection', 'HK', 'inspection', ['Main entrance glass and mats', 'Lobby furniture and floors', 'Lobby washrooms stocked and clean', 'Elevator cabs and landings', 'Corridors vacuumed, no room service trays', 'Ice and vending areas clean', 'Fitness centre equipment wiped, towels stocked']);
        $tpl('Kitchen opening (HACCP)', 'KIT', 'opening', ['Hand sinks stocked with soap and paper towel', 'Sanitiser buckets at 200 ppm quat', 'Walk-in cooler 0 to 4 °C logged', 'Walk-in freezer -18 °C or colder logged', 'Line reach-ins logged', 'Dish machine final rinse at 82 °C or above', 'Date labels checked, expired product discarded', 'Probe thermometers calibrated (ice point)', 'Pest activity check']);
        $tpl('Kitchen closing', 'KIT', 'closing', ['Food covered, labelled and dated', 'Cooling logs complete (60 to 21 °C in 2 h, to 4 °C in 4 h more)', 'Equipment off and cleaned', 'Hood filters wiped', 'Floors swept and mopped', 'Garbage removed', 'Walk-in doors closed and latched']);
        $tpl('Bar opening', 'FB', 'opening', ['Ice bins clean and full', 'Glass washer temperature and chemical', 'Garnishes fresh and dated', 'Float counted', 'Draft lines and taps clean', 'Responsible alcohol service signage posted']);
        $tpl('Fire extinguisher monthly inspection', 'ENG', 'fire', ['In designated place, visible, unobstructed', 'Pressure gauge in operable range', 'Safety pin and tamper seal intact', 'No physical damage, corrosion or leakage', 'Inspection tag initialled and dated'], 100);
        $tpl('Fire alarm weekly test', 'ENG', 'fire', ['Notify monitoring company before test', 'Operate one manual pull station (rotate)', 'Confirm panel received alarm', 'Confirm bells and strobes on test floor', 'Reset panel, confirm normal', 'Notify monitoring company test complete', 'Record station tested'], 100);
        $tpl('Emergency lighting monthly test', 'ENG', 'fire', ['Activate test switch on each unit for 30 seconds', 'All lamps illuminate', 'Exit signs illuminated', 'Batteries charging light on after test']);
        $tpl('Generator monthly run', 'ENG', 'pm', ['Check oil, coolant and fuel levels', 'Block heater working (winter)', 'Run under load 30 minutes', 'Record hours, voltage, frequency', 'Transfer switch returns to normal', 'Check for leaks']);
        $tpl('Boiler daily rounds', 'ENG', 'pm', ['Supply and return temperatures recorded', 'System pressure in range', 'Low water cut-off test (weekly)', 'Flue and venting clear of frost build-up', 'No leaks at pumps and valves', 'Glycol level in make-up tank']);
        $tpl('Guest room preventive maintenance', 'ENG', 'pm', ['Test smoke detector', 'Check door closer, latch and lock battery', 'Tighten furniture and fixtures', 'Clean PTAC / fan coil filter and coil', 'Check caulking at tub and sink', 'Toilet flush and fill valve', 'Shower head and aerators descaled', 'Check window seals for drafts and frost', 'Test all outlets and GFCI', 'Touch-up paint as needed']);
        $tpl('Security night patrol', 'SEC', 'safety', ['All exterior doors secure', 'Parking lot and EV chargers checked', 'Stairwells clear, doors latch', 'Pool and fitness locked at close', 'Loading dock secure', 'Mechanical rooms locked', 'Fire panel normal', 'CCTV recording on all cameras']);
        $tpl('Banquet room set-up check', 'BQ', 'inspection', ['Set-up matches BEO style and count', 'Linens pressed, no stains', 'Head table and staging as ordered', 'AV tested with client device', 'Room temperature 21 °C', 'Water stations set', 'Signage placed', 'Fire exits clear']);
        $tpl('Night audit checklist', 'FO', 'closing', ['Post room and tax', 'Run in-house balance report', 'Check no-shows and cancellations', 'Verify cash drop', 'Back up reports', 'Print arrivals and VIP list for morning', 'Review logbook and pass on items']);

        // ---------- categories ----------
        $C = [];
        $cat = function (string $dept, string $name, string $type, string $pri, ?string $sla, string $kw, bool $guest = false, ?string $glabel = null, ?string $icon = null, ?string $tplName = null) use (&$C, $ins, $D, $S, $T, $now) {
            static $sort = 0;
            $C[$name] = $ins('categories', ['department_id' => $D[$dept], 'name' => $name, 'type' => $type, 'default_priority' => $pri, 'sla_policy_id' => $sla ? $S[$sla] : null, 'keywords' => $kw, 'guest_visible' => $guest ? 1 : 0,
                'guest_label' => $glabel, 'icon' => $icon, 'checklist_template_id' => $tplName ? $T[$tplName] : null, 'sort' => $sort++, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        };
        $cat('HK', 'Extra towels or linen', 'guest_request', 'normal', 'Guest amenity (20 min promise)', 'towel,towels,linen,sheets,blanket,pillow,duvet,bath mat', true, 'Towels, pillows or blankets', '🛏️');
        $cat('HK', 'Amenities', 'guest_request', 'normal', 'Guest amenity (20 min promise)', 'shampoo,toothbrush,toothpaste,razor,toiletries,soap,slippers,robe,coffee pods,tissue,toilet paper,crib,rollaway,iron', true, 'Toiletries, coffee, crib or rollaway', '🧴');
        $cat('HK', 'Room cleaning', 'guest_request', 'normal', 'Guest comfort (in-room)', 'clean my room,make up,housekeeping,vacuum,garbage,trash,dirty', true, 'Clean my room', '🧹');
        $cat('HK', 'Turndown', 'guest_request', 'low', null, 'turndown,turn down', true, 'Evening turndown', '🌙');
        $cat('HK', 'Spill or stain', 'work_order', 'normal', 'Guest comfort (in-room)', 'spill,stain,vomit,broken glass', false);
        $cat('HK', 'Public area cleaning', 'task', 'normal', 'Normal', 'lobby,washroom,corridor,hallway,elevator floor', false);
        $cat('ENG', 'Heating or cooling', 'work_order', 'high', 'Guest comfort (in-room)', 'heat,heating,cold,too hot,thermostat,air conditioning,hvac,ptac,fan coil,radiator,freezing,draft,drafty', true, 'Room too hot or too cold', '🌡️', 'Guest room preventive maintenance');
        $cat('ENG', 'Plumbing', 'work_order', 'high', 'Guest comfort (in-room)', 'toilet,leak,drain,clog,shower,sink,faucet,water pressure,hot water,tub,flood,running water', true, 'Toilet, shower or sink problem', '🚿');
        $cat('ENG', 'Electrical and lighting', 'work_order', 'normal', 'Normal', 'light,lamp,bulb,outlet,power,plug,breaker,switch', true, 'Lights or power outlets', '💡');
        $cat('ENG', 'Furniture and fixtures', 'work_order', 'normal', 'Normal', 'chair,bed,drawer,curtain,blind,drape,closet,mirror,hinge,shelf,desk,headboard', true, 'Furniture, drapes or fixtures', '🪑');
        $cat('ENG', 'Door and lock hardware', 'work_order', 'high', 'High', 'door,lock battery,door closer,latch,deadbolt,peephole', false);
        $cat('ENG', 'Elevator', 'work_order', 'urgent', 'Urgent', 'elevator,lift', false);
        $cat('ENG', 'Fire and life safety', 'work_order', 'critical', 'Critical: life safety / outage', 'smoke detector,fire alarm,sprinkler,extinguisher,exit sign,emergency light,pull station,carbon monoxide', false);
        $cat('ENG', 'Pest control', 'work_order', 'high', 'High', 'bug,bed bug,bedbug,mouse,mice,cockroach,insect,pest', false);
        $cat('ENG', 'Parking and EV charging', 'work_order', 'high', 'High', 'ev charger,charger,charging,parking,car won\'t start,garage', true, 'Parking or EV charging', '🔌');
        $cat('ENG', 'Snow and ice', 'task', 'high', 'High', 'snow,ice,icy,salt,sand,sidewalk,slippery', false);
        $cat('ENG', 'Kitchen equipment', 'work_order', 'urgent', 'Urgent', 'fryer,oven,walk-in,cooler,freezer,dish machine,dishwasher,grill,hood,range,steam kettle,ice machine', false);
        $cat('ENG', 'Laundry equipment', 'work_order', 'high', 'High', 'washer,dryer,ironer,folder,lint', false);
        $cat('ENG', 'Preventive maintenance', 'pm', 'normal', 'Normal', '', false);
        $cat('FO', 'Key cards and lock-outs', 'guest_request', 'high', 'Guest amenity (20 min promise)', 'key card,keycard,locked out,card not working,room key', true, 'Room key not working', '🔑');
        $cat('FO', 'Wake-up call', 'guest_request', 'normal', null, 'wake up,wakeup,wake-up,alarm call', true, 'Wake-up call', '⏰');
        $cat('FO', 'Late check-out', 'guest_request', 'normal', null, 'late check out,late checkout,late check-out,extend stay', true, 'Late check-out', '🕐');
        $cat('FO', 'Billing question', 'guest_request', 'normal', 'Normal', 'bill,folio,charge,invoice,receipt,refund', true, 'Question about my bill', '🧾');
        $cat('FO', 'Transportation', 'guest_request', 'normal', 'Guest comfort (in-room)', 'taxi,cab,shuttle,airport,car rental', true, 'Taxi or airport shuttle', '🚕');
        $cat('FO', 'Noise complaint', 'complaint', 'high', 'Guest complaint', 'noise,noisy,loud,party,neighbour,neighbor,music', true, 'Noise complaint', '🔇');
        $cat('FO', 'Guest complaint (general)', 'complaint', 'high', 'Guest complaint', 'complaint,unhappy,disappointed,speak to manager,terrible,unacceptable', false);
        $cat('FO', 'Packages and deliveries', 'task', 'normal', 'Normal', 'package,delivery,parcel,courier,amazon', false);
        $cat('FB', 'In-room dining order', 'guest_request', 'high', 'In-room dining (35 min promise)', 'room service,order food,in-room dining,breakfast in room,hungry', true, 'Order in-room dining', '🍳');
        $cat('FB', 'Tray pick-up', 'guest_request', 'normal', 'Guest amenity (20 min promise)', 'tray,dishes,pick up dishes,room service tray', true, 'Pick up my tray', '🍽️');
        $cat('FB', 'Restaurant reservation', 'guest_request', 'normal', null, 'table,reservation,dinner reservation,book a table', true, 'Restaurant reservation', '📅');
        $cat('KIT', 'Dietary or allergy request', 'guest_request', 'high', 'High', 'allergy,allergic,gluten,celiac,vegan,vegetarian,halal,kosher,nut,dairy free,dietary', true, 'Allergy or dietary need', '🥗');
        $cat('KIT', 'Food safety', 'work_order', 'urgent', 'Urgent', 'temperature,haccp,food safety,expired', false);
        $cat('BQ', 'Event set-up change', 'event_task', 'high', 'High', 'set-up,setup,banquet,meeting room,ballroom,breakout', false);
        $cat('IT', 'Wi-Fi and internet', 'guest_request', 'high', 'Guest comfort (in-room)', 'wifi,wi-fi,internet,network,connection,slow internet,can\'t connect', true, 'Wi-Fi or internet', '📶');
        $cat('IT', 'TV and entertainment', 'work_order', 'normal', 'Guest comfort (in-room)', 'tv,television,remote,channel,chromecast,netflix,casting', true, 'TV or streaming', '📺');
        $cat('IT', 'Meeting room AV', 'work_order', 'high', 'High', 'projector,microphone,screen,av,hdmi,speaker,zoom,teams room', false);
        $cat('IT', 'Staff systems (Opera, POS, printers)', 'work_order', 'high', 'High', 'opera,pos,micros,printer,computer,email,workstation,card reader,terminal', false);
        $cat('IT', 'Phone system', 'work_order', 'normal', 'Normal', 'phone,dial tone,voicemail,pbx', true, 'Room phone', '☎️');
        $cat('SEC', 'Safety or security concern', 'incident', 'urgent', 'Urgent', 'security,suspicious,theft,stolen,intoxicated,fight,threat,trespass,unsafe', true, 'Safety or security concern', '🛡️');
        $cat('LND', 'Guest laundry and dry cleaning', 'guest_request', 'normal', null, 'laundry,dry cleaning,pressing,press my,ironing service', true, 'Laundry or dry cleaning', '👔');
        $cat('LND', 'Linen shortage', 'task', 'high', 'High', 'par,linen shortage,out of towels,no sheets', false);

        // ---------- locations ----------
        $L = [];
        $loc = function (string $code, string $name, string $type, ?string $floor, ?string $dept, ?string $parent = null, bool $qr = false) use (&$L, $ins, $D, $now) {
            $L[$code] = $ins('locations', ['code' => $code, 'name' => $name, 'type' => $type, 'floor' => $floor, 'department_id' => $dept ? $D[$dept] : null, 'parent_id' => $parent ? $L[$parent] : null, 'qr_token' => $qr ? U::token(12) : null, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        };
        $loc('BLDG', 'Main building', 'building', null, 'ENG');
        foreach (range(1, 8) as $f) $loc("F$f", "Floor $f", 'floor', (string)$f, 'HK', 'BLDG');
        $loc('LOBBY', 'Main lobby', 'area', '1', 'HK', 'F1', true);
        $loc('LOBWC', 'Lobby washrooms', 'area', '1', 'HK', 'F1', true);
        $loc('FD', 'Front desk', 'area', '1', 'FO', 'F1');
        $loc('REST', 'Main restaurant', 'outlet', '1', 'FB', 'F1', true);
        $loc('LOUNGE', 'Lobby lounge', 'outlet', '1', 'FB', 'F1', true);
        $loc('KITCHEN', 'Main kitchen', 'boh', '1', 'KIT', 'F1');
        $loc('BQKIT', 'Banquet kitchen', 'boh', '1', 'KIT', 'F1');
        $loc('BALLA', 'Ballroom A', 'meeting', '1', 'BQ', 'F1', true);
        $loc('BALLB', 'Ballroom B', 'meeting', '1', 'BQ', 'F1', true);
        $loc('BOARD1', 'Boardroom 1', 'meeting', '2', 'BQ', 'F2', true);
        $loc('BOARD2', 'Boardroom 2', 'meeting', '2', 'BQ', 'F2', true);
        $loc('FIT', 'Fitness centre', 'area', '1', 'HK', 'F1', true);
        $loc('BIZ', 'Business centre', 'area', '1', 'IT', 'F1', true);
        $loc('LAUNDRY', 'Laundry', 'boh', 'B', 'LND', 'BLDG');
        $loc('BOILER', 'Boiler room', 'mechanical', 'B', 'ENG', 'BLDG');
        $loc('ELEC', 'Main electrical room', 'mechanical', 'B', 'ENG', 'BLDG');
        $loc('PENT', 'Mechanical penthouse', 'mechanical', 'R', 'ENG', 'BLDG');
        $loc('DOCK', 'Loading dock and receiving', 'boh', '1', 'ENG', 'BLDG');
        $loc('SECOFF', 'Security office', 'boh', '1', 'SEC', 'F1');
        $loc('SERVER', 'Server and network room', 'boh', '1', 'IT', 'F1');
        $loc('CAFE', 'Staff cafeteria', 'boh', 'B', 'KIT', 'BLDG');
        $loc('PARK', 'Guest parking and EV chargers', 'parking', null, 'ENG', 'BLDG', true);
        $loc('ENTR', 'Main entrance and canopy', 'exterior', '1', 'ENG', 'BLDG');
        $loc('ROOF', 'Roof', 'exterior', 'R', 'ENG', 'BLDG');

        // ---------- room types and rooms (8 guest floors, 2 to 8, 26 rooms each = 182) ----------
        $RT = [];
        foreach ([['KNG', 'Standard King', 1.0, 30, '1 King', 2], ['QQ', 'Two Queen', 1.2, 35, '2 Queen', 4], ['EXK', 'Executive King', 1.2, 35, '1 King, work area', 2], ['ACC', 'Accessible King (roll-in shower)', 1.2, 35, '1 King', 2], ['STE', 'One-Bedroom Suite', 2.0, 55, '1 King + sofa bed', 4]] as [$c, $n, $cr, $m, $beds, $occ]) {
            $RT[$c] = $ins('room_types', ['code' => $c, 'name' => $n, 'hk_credits' => $cr, 'clean_minutes' => $m, 'beds' => $beds, 'max_occupancy' => $occ, 'created_at' => $now, 'updated_at' => $now]);
        }
        $hkStaff = array_map(fn($i) => $U['hk' . $i], range(1, 6));
        $rooms = [];
        foreach (range(2, 8) as $f) {
            foreach (range(1, 26) as $n) {
                $num = $f . str_pad((string)$n, 2, '0', STR_PAD_LEFT);
                $type = $n === 1 || $n === 26 ? 'STE' : ($n === 2 && $f <= 3 ? 'ACC' : ($f >= 7 ? 'EXK' : ($n % 2 ? 'QQ' : 'KNG')));
                $rooms[$num] = $ins('rooms', ['number' => $num, 'floor' => (string)$f, 'room_type_id' => $RT[$type], 'location_id' => $L["F$f"], 'qr_token' => U::token(12), 'opera_room_id' => $num,
                    'hk_section' => 'S' . (int)ceil(($f - 1) / 1), 'features' => $f >= 6 ? 'Lake view' : ($n <= 13 ? 'City view' : 'Courtyard'), 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        // Occupancy picture: about 72 per cent occupied, a morning-after-night-audit state that the day then plays out.
        $i = 0;
        $vipCodes = ['VIP1', 'VIP2', 'GOLD', 'PLAT'];
        foreach ($rooms as $num => $rid) {
            $num = (string)$num;
            $i++;
            $r = mt_rand(1, 100);
            $guest = self::name($i * 3 + 1);
            $att = $hkStaff[intdiv(((int)$num[0] - 2) * 26 + (int)substr($num, 1), 31) % 6];
            $base = ['attendant_id' => $att, 'updated_at' => $now];
            if (in_array($num, ['317', '612'], true)) {
                Db::update('rooms', $base + ['service_status' => 'ooo', 'hk_status' => 'dirty', 'fo_status' => 'vacant', 'ooo_reason' => $num === '317' ? 'Bathroom ceiling leak from 417, drywall repair' : 'Carpet replacement after flood', 'ooo_until' => U::localDate(time() + 2 * 86400)], $rid);
                continue;
            }
            if ($r <= 40) { // stayover
                Db::update('rooms', $base + ['fo_status' => 'occupied', 'reservation_status' => 'stayover', 'hk_status' => mt_rand(1, 10) <= 4 ? 'clean' : 'dirty', 'guest_name' => $guest, 'reservation_ref' => (string)mt_rand(48000000, 48999999),
                    'arrival_date' => U::localDate(time() - mt_rand(1, 4) * 86400), 'departure_date' => U::localDate(time() + mt_rand(1, 5) * 86400), 'adults' => mt_rand(1, 2), 'dnd' => mt_rand(1, 12) === 1 ? 1 : 0, 'eco_skip' => mt_rand(1, 15) === 1 ? 1 : 0,
                    'vip_code' => mt_rand(1, 14) === 1 ? $vipCodes[array_rand($vipCodes)] : null, 'last_cleaned_at' => self::ago(mt_rand(60, 1500))], $rid);
            } elseif ($r <= 62) { // due out
                Db::update('rooms', $base + ['fo_status' => 'occupied', 'reservation_status' => 'due_out', 'hk_status' => 'dirty', 'guest_name' => $guest, 'reservation_ref' => (string)mt_rand(48000000, 48999999), 'arrival_date' => U::localDate(time() - mt_rand(1, 5) * 86400), 'departure_date' => U::localDate(), 'adults' => mt_rand(1, 2)], $rid);
            } elseif ($r <= 72) { // departed already, dirty
                Db::update('rooms', $base + ['fo_status' => 'vacant', 'reservation_status' => 'none', 'hk_status' => 'dirty'], $rid);
            } else { // vacant ready
                Db::update('rooms', $base + ['fo_status' => 'vacant', 'reservation_status' => 'none', 'hk_status' => mt_rand(1, 3) === 1 ? 'clean' : 'inspected', 'last_cleaned_at' => self::ago(mt_rand(300, 3000)), 'last_inspected_at' => self::ago(mt_rand(200, 2900))], $rid);
            }
        }
        self::dailyArrivals();
        // Pin the guests that the demo tickets talk about to their rooms.
        foreach ([['514', 'Harper Lindqvist', 'stayover', null], ['706', 'Wren Ellison', 'stayover', null], ['822', 'Arjun Delorme', 'stayover', null], ['309', 'Mei Okafor', 'stayover', null], ['720', 'Samir Whitford', 'due_out', null],
            ['801', 'Ingrid Hughes', 'stayover', 'VIP1'], ['524', 'Tomas Carter', 'stayover', null], ['223', 'Priya Kowalski', 'stayover', null], ['610', 'Kendall Fraser', 'stayover', null], ['412', 'Oskar Bergeron', 'stayover', null], ['615', 'Leilani Gill', 'stayover', null], ['315', 'Hana Petrov', 'stayover', null]] as [$num, $g, $rs, $vip]) {
            Db::update('rooms', ['fo_status' => 'occupied', 'reservation_status' => $rs, 'guest_name' => $g, 'vip_code' => $vip, 'hk_status' => 'dirty', 'service_status' => 'in_service', 'reservation_ref' => (string)mt_rand(48000000, 48999999),
                'arrival_date' => U::localDate(time() - 2 * 86400), 'departure_date' => $rs === 'due_out' ? U::localDate() : U::localDate(time() + 2 * 86400), 'adults' => 2], $rooms[$num]);
        }
        Db::update('rooms', ['fo_status' => 'vacant', 'reservation_status' => 'none', 'guest_name' => null, 'vip_code' => null], $rooms['422']);
        Db::q("UPDATE rooms SET hk_status = 'in_progress', hk_started_at = ? WHERE number IN ('204','211','405','508','703')", [self::ago(12)]);
        Db::q("UPDATE rooms SET rush = 1 WHERE reservation_status = 'arrival' AND vip_code IS NOT NULL");

        // ---------- outlets, temperature points ----------
        $O = [];
        foreach ([['Main Restaurant', 'restaurant', 'REST', '6:30 a.m. to 10 p.m.', 120], ['Lobby Lounge', 'lounge', 'LOUNGE', '11 a.m. to midnight', 60], ['In-Room Dining', 'in_room_dining', 'KITCHEN', '6:30 a.m. to 11 p.m.', null], ['Banquets', 'banquets', 'BALLA', 'By event', 400], ['Staff Cafeteria', 'staff_cafeteria', 'CAFE', '24 hours', 30]] as [$n, $k, $l, $h, $cap]) {
            $O[$n] = $ins('outlets', ['name' => $n, 'kind' => $k, 'location_id' => $L[$l], 'hours' => $h, 'manager_id' => $k === 'banquets' ? $U['events'] : $U['fb.manager'], 'capacity' => $cap, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $TP = [];
        foreach ([['Walk-in cooler', 'fridge', 'Main Restaurant', 0, 4, 4, 'sensor-wic-01'], ['Walk-in freezer', 'freezer', 'Main Restaurant', -30, -18, 4, 'sensor-wif-01'], ['Line reach-in 1', 'fridge', 'Main Restaurant', 0, 4, 4, null], ['Line reach-in 2 (salad)', 'fridge', 'Main Restaurant', 0, 4, 4, null],
            ['Lounge bar fridge', 'fridge', 'Lobby Lounge', 0, 4, 8, null], ['Banquet hot box', 'hot_holding', 'Banquets', 60, 90, 2, null], ['Dish machine final rinse', 'dishwasher', 'Main Restaurant', 82, 95, 8, null], ['Receiving probe', 'receiving', 'Main Restaurant', 0, 4, 24, null], ['Banquet kitchen cooler', 'fridge', 'Banquets', 0, 4, 4, null]] as [$n, $k, $o, $mi, $ma, $ev, $sid]) {
            $TP[$n] = $ins('temp_points', ['name' => $n, 'kind' => $k, 'outlet_id' => $O[$o], 'min_c' => $mi, 'max_c' => $ma, 'check_every_hours' => $ev, 'sensor_id' => $sid, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ($TP as $n => $pid) {
            $pt = Db::one('SELECT * FROM temp_points WHERE id = ?', [$pid]);
            for ($h = 72; $h >= 1; $h -= (int)$pt['check_every_hours']) {
                $mid = ((float)$pt['min_c'] + (float)$pt['max_c']) / 2;
                $v = round($mid + (mt_rand(-100, 100) / 100) * ((float)$pt['max_c'] - (float)$pt['min_c']) / 3, 1);
                Db::insert('temp_logs', ['point_id' => $pid, 'reading_c' => $v, 'in_range' => 1, 'source' => $pt['sensor_id'] ? 'sensor' : 'manual', 'recorded_by' => $pt['sensor_id'] ? null : $U['cook1'], 'created_at' => self::ago($h * 60 + mt_rand(0, 30))]);
            }
        }

        // ---------- vendors, assets, parts ----------
        $V = [];
        foreach ([['Summit Elevator (demo)', 'Elevator maintenance', 'Pat Lindqvist', '555-0101', '555-0199', '+300'], ['Pinnacle Mechanical (demo)', 'HVAC, boilers, plumbing', 'Sam Kerr', '555-0122', '555-0123', '+20'], ['Lakeside Fire Protection (demo)', 'Fire alarm, sprinklers, extinguishers', 'Lee Tanaka', '555-0144', '555-0145', '+25'],
            ['ColdLine Refrigeration (demo)', 'Commercial refrigeration', 'Dana Morrow', '555-0166', '555-0167', '+12'], ['Citywide Pest Solutions (demo)', 'Pest control', 'Chris Abara', '555-0188', null, '+200'], ['Brightline Electric (demo)', 'Electrical', 'Jesse Moreau', '555-0110', '555-0111', '+90'],
            ['Kitchen Hood Services (demo)', 'Hood and duct cleaning', 'Ari Feld', '555-0133', null, '+150'], ['Clearway Snow Removal (demo)', 'Snow clearing and sanding', 'Bo Strand', '555-0155', '555-0156', '+18'], ['Example IT Services (demo)', 'Network, Wi-Fi, IT support', 'Service desk', '555-0177', '555-0178', '+330']] as $i => [$n, $tr, $cn, $ph, $em, $ins_days]) {
            $V[$n] = $ins('vendors', ['name' => $n, 'trade' => $tr, 'contact_name' => $cn, 'phone' => $ph, 'emergency_phone' => $em, 'email' => 'service@vendor' . ($i + 1) . '.demo', 'insurance_expiry' => U::localDate(time() + (int)$ins_days * 86400), 'workers_comp_expiry' => U::localDate(time() + ((int)$ins_days + 40) * 86400), 'rating' => mt_rand(3, 5), 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $A = [];
        $asset = function (string $tag, string $name, string $cat, string $loc, string $dept, string $mfr, string $model, string $status = 'operational', string $crit = 'medium', ?string $vendor = null, int $yearsOld = 8) use (&$A, $ins, $L, $D, $V, $now) {
            $A[$tag] = $ins('assets', ['tag' => $tag, 'name' => $name, 'category' => $cat, 'location_id' => $L[$loc], 'department_id' => $D[$dept], 'manufacturer' => $mfr, 'model' => $model, 'serial' => strtoupper(substr(md5($tag), 0, 10)),
                'status' => $status, 'criticality' => $crit, 'vendor_id' => $vendor ? $V[$vendor] : null, 'install_date' => U::localDate(time() - $yearsOld * 365 * 86400), 'warranty_until' => U::localDate(time() + ($yearsOld < 3 ? 400 : -200) * 86400),
                'expected_life_years' => 20, 'qr_token' => U::token(12), 'created_at' => $now, 'updated_at' => $now]);
        };
        $asset('BLR-01', 'Boiler 1 (heating)', 'Boiler', 'BOILER', 'ENG', 'Viessmann', 'Vitocrossal 300', 'operational', 'life_safety', 'Pinnacle Mechanical (demo)', 11);
        $asset('BLR-02', 'Boiler 2 (heating)', 'Boiler', 'BOILER', 'ENG', 'Viessmann', 'Vitocrossal 300', 'operational', 'life_safety', 'Pinnacle Mechanical (demo)', 11);
        $asset('DHW-01', 'Domestic hot water heater 1', 'Water heater', 'BOILER', 'ENG', 'A.O. Smith', 'Cyclone Xi', 'operational', 'high', 'Pinnacle Mechanical (demo)', 6);
        $asset('DHW-02', 'Domestic hot water heater 2', 'Water heater', 'BOILER', 'ENG', 'A.O. Smith', 'Cyclone Xi', 'degraded', 'high', 'Pinnacle Mechanical (demo)', 6);
        $asset('MAU-01', 'Make-up air unit (corridors)', 'HVAC', 'PENT', 'ENG', 'Engineered Air', 'HE-Series', 'operational', 'high', 'Pinnacle Mechanical (demo)', 12);
        $asset('HRV-01', 'Heat recovery ventilator', 'HVAC', 'PENT', 'ENG', 'Venmar', 'Commercial', 'operational', 'medium', 'Pinnacle Mechanical (demo)', 9);
        $asset('ELV-01', 'Passenger elevator 1', 'Elevator', 'F1', 'ENG', 'Otis', 'Gen2', 'operational', 'high', 'Summit Elevator (demo)', 15);
        $asset('ELV-02', 'Passenger elevator 2', 'Elevator', 'F1', 'ENG', 'Otis', 'Gen2', 'down', 'high', 'Summit Elevator (demo)', 15);
        $asset('GEN-01', 'Emergency generator', 'Generator', 'ELEC', 'ENG', 'Cummins', 'C150 D6', 'operational', 'life_safety', 'Brightline Electric (demo)', 14);
        $asset('FAP-01', 'Fire alarm panel', 'Fire', 'SECOFF', 'ENG', 'Edwards', 'EST3', 'operational', 'life_safety', 'Lakeside Fire Protection (demo)', 10);
        $asset('SPR-01', 'Sprinkler riser and backflow', 'Fire', 'BOILER', 'ENG', 'Watts', 'LF909', 'operational', 'life_safety', 'Lakeside Fire Protection (demo)', 10);
        $asset('WIC-01', 'Walk-in cooler', 'Refrigeration', 'KITCHEN', 'KIT', 'Kolpak', 'KF7', 'operational', 'high', 'ColdLine Refrigeration (demo)', 7);
        $asset('WIF-01', 'Walk-in freezer', 'Refrigeration', 'KITCHEN', 'KIT', 'Kolpak', 'KF7', 'operational', 'high', 'ColdLine Refrigeration (demo)', 7);
        $asset('KIT-COMBI', 'Combi oven', 'Cooking', 'KITCHEN', 'KIT', 'Rational', 'iCombi Pro 10-1/1', 'operational', 'high', null, 3);
        $asset('KIT-DISH', 'Dish machine', 'Warewashing', 'KITCHEN', 'KIT', 'Hobart', 'CL44e', 'operational', 'high', null, 5);
        $asset('KIT-FRY1', 'Fryer 1', 'Cooking', 'KITCHEN', 'KIT', 'Frymaster', 'H55', 'degraded', 'medium', null, 8);
        $asset('KIT-HOOD', 'Kitchen exhaust hood and Ansul', 'Fire', 'KITCHEN', 'KIT', 'Captive-Aire', 'ND-2', 'operational', 'life_safety', 'Kitchen Hood Services (demo)', 8);
        foreach ([3, 5, 7] as $f) $asset("ICE-0$f", "Ice machine floor $f", 'Ice machine', "F$f", 'ENG', 'Scotsman', 'Prodigy', $f === 5 ? 'down' : 'operational', 'low', null, 6);
        $asset('LND-W1', 'Washer-extractor 1 (60 kg)', 'Laundry', 'LAUNDRY', 'LND', 'Milnor', '36026', 'operational', 'high', null, 9);
        $asset('LND-W2', 'Washer-extractor 2 (60 kg)', 'Laundry', 'LAUNDRY', 'LND', 'Milnor', '36026', 'operational', 'high', null, 9);
        $asset('LND-D1', 'Dryer 1', 'Laundry', 'LAUNDRY', 'LND', 'Cissell', 'Gas 75 lb', 'operational', 'high', null, 9);
        $asset('LND-D2', 'Dryer 2', 'Laundry', 'LAUNDRY', 'LND', 'Cissell', 'Gas 75 lb', 'degraded', 'high', null, 9);
        $asset('LND-IRN', 'Flatwork ironer', 'Laundry', 'LAUNDRY', 'LND', 'Chicago', 'Imperial', 'operational', 'medium', null, 12);
        $asset('PARK-P1', 'EV charger bank A (bays 1 to 8)', 'Electrical', 'PARK', 'ENG', 'ChargePoint', 'CPF50', 'operational', 'high', 'Brightline Electric (demo)', 10);
        $asset('PARK-P2', 'EV charger bank B (bays 9 to 16)', 'Electrical', 'PARK', 'ENG', 'ChargePoint', 'CPF50', 'degraded', 'high', 'Brightline Electric (demo)', 10);
        $asset('SNOW-01', 'Snow blower', 'Grounds', 'DOCK', 'ENG', 'Toro', 'Power Max HD', 'operational', 'medium', null, 4);
        $asset('NET-CORE', 'Core switch', 'Network', 'SERVER', 'IT', 'Ubiquiti', 'Pro XG Aggregation', 'operational', 'high', 'Example IT Services (demo)', 1);
        $asset('NET-WIFI', 'Guest Wi-Fi system (54 APs)', 'Network', 'SERVER', 'IT', 'Ubiquiti', 'U7 Pro Max', 'operational', 'high', 'Example IT Services (demo)', 1);
        $asset('PBX-01', 'Phone system', 'Telephony', 'SERVER', 'IT', 'Mitel', 'MiVoice', 'operational', 'medium', null, 9);
        $asset('POS-01', 'Restaurant POS terminals (4)', 'POS', 'REST', 'IT', 'Oracle', 'Simphony Workstation', 'operational', 'high', null, 4);
        $asset('CCTV-NVR', 'CCTV recorder', 'Security', 'SECOFF', 'SEC', 'Synology', 'Surveillance Station', 'operational', 'high', 'Example IT Services (demo)', 1);
        $asset('BALL-AV', 'Ballroom AV system', 'AV', 'BALLA', 'IT', 'Extron', 'DTP', 'operational', 'medium', null, 6);

        $P = [];
        foreach ([['FLT-2020', 'Furnace filter 20x20x2 MERV 8', 'Filters', 'ENG', 48, 24, 0, 6.5], ['FLT-PTAC', 'PTAC filter set', 'Filters', 'ENG', 30, 40, 0, 4.2], ['LMP-A19', 'LED A19 bulb 2700K', 'Electrical', 'ENG', 120, 50, 0, 3.1], ['LMP-PAR', 'LED PAR30 bulb', 'Electrical', 'ENG', 14, 20, 0, 9.8],
            ['FLP-KIT', 'Toilet flapper universal', 'Plumbing', 'ENG', 25, 10, 0, 7.4], ['FV-400', 'Toilet fill valve', 'Plumbing', 'ENG', 12, 8, 0, 18.5], ['SHW-HEAD', 'Shower head 1.8 gpm', 'Plumbing', 'ENG', 9, 10, 0, 32.0], ['CAULK-W', 'Silicone caulk white', 'Plumbing', 'ENG', 22, 10, 0, 8.9],
            ['BATT-AA', 'AA batteries (remotes, locks)', 'Electrical', 'ENG', 200, 100, 0, 0.6], ['SMK-DET', 'Smoke detector 120V', 'Life safety', 'ENG', 6, 6, 0, 46.0], ['ICE-SALT', 'Ice melt 20 kg', 'Grounds', 'ENG', 18, 30, 0, 24.0],
            ['TWL-BATH', 'Bath towel', 'Linen', 'HK', 820, 900, 1100, 11.5], ['TWL-HAND', 'Hand towel', 'Linen', 'HK', 760, 800, 1000, 5.2], ['SHT-K', 'King flat sheet', 'Linen', 'HK', 450, 420, 600, 21.0], ['SHT-Q', 'Queen flat sheet', 'Linen', 'HK', 380, 400, 560, 18.0],
            ['AMN-SHMP', 'Shampoo 30 ml', 'Amenities', 'HK', 1600, 800, 2400, 0.42], ['AMN-COND', 'Conditioner 30 ml', 'Amenities', 'HK', 1450, 800, 2400, 0.42], ['AMN-SOAP', 'Bar soap 40 g', 'Amenities', 'HK', 2100, 800, 2400, 0.35], ['AMN-COF', 'Coffee pods', 'Amenities', 'HK', 650, 700, 1500, 0.55], ['AMN-TP', 'Toilet paper roll', 'Amenities', 'HK', 940, 600, 1500, 0.48],
            ['CHEM-QUAT', 'Quat sanitiser 4 L', 'Chemicals', 'KIT', 6, 4, 0, 38.0], ['CHEM-DET', 'Dish detergent 20 L', 'Chemicals', 'KIT', 3, 2, 0, 140.0], ['CHEM-LND', 'Laundry detergent 20 L', 'Chemicals', 'LND', 5, 3, 0, 120.0],
            ['MB-WATER', 'Minibar water', 'Minibar', 'FB', 340, 200, 400, 0.9], ['MB-CHIPS', 'Minibar chips', 'Minibar', 'FB', 160, 120, 300, 1.4]] as [$sku, $n, $c, $dept, $qty, $rp, $par, $cost]) {
            $P[$sku] = $ins('parts', ['sku' => $sku, 'name' => $n, 'category' => $c, 'department_id' => $D[$dept], 'qty_on_hand' => $qty, 'reorder_point' => $rp, 'reorder_qty' => max($rp, 10) * 2, 'par_level' => $par, 'unit_cost' => $cost, 'unit' => 'each', 'storage_location' => $dept === 'HK' ? 'Linen room B1' : ($dept === 'ENG' ? 'Engineering stores' : 'Dry storage'), 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // ---------- PM schedules ----------
        $pm = function (string $name, ?string $asset, string $dept, string $freq, int $dueInDays, ?string $tplName, string $compliance, string $assign, int $mins = 60, ?string $loc = null) use ($ins, $A, $D, $T, $U, $C, $L, $now) {
            $ins('pm_schedules', ['name' => $name, 'asset_id' => $asset ? $A[$asset] : null, 'location_id' => $loc ? $L[$loc] : null, 'department_id' => $D[$dept], 'category_id' => $C['Preventive maintenance'], 'frequency' => $freq, 'interval_days' => 30, 'next_due' => U::localDate(time() + $dueInDays * 86400), 'lead_days' => 2,
                'checklist_template_id' => $tplName ? $T[$tplName] : null, 'assigned_to' => $U[$assign] ?? null, 'priority' => 'normal', 'estimated_minutes' => $mins, 'compliance' => $compliance, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        };
        $pm('Boiler daily rounds', 'BLR-01', 'ENG', 'daily', 0, 'Boiler daily rounds', 'Boiler and pressure vessel regulations (operator rounds)', 'eng1', 30);
        $pm('Fire alarm weekly test', 'FAP-01', 'ENG', 'weekly', 1, 'Fire alarm weekly test', 'NFPA 72 / local fire code', 'eng.super', 30);
        $pm('Fire extinguisher monthly inspection', null, 'ENG', 'monthly', 4, 'Fire extinguisher monthly inspection', 'NFPA 10 / local fire code', 'eng2', 90, 'BLDG');
        $pm('Emergency lighting monthly test', null, 'ENG', 'monthly', 9, 'Emergency lighting monthly test', 'NFPA 101 / local fire code', 'eng2', 60, 'BLDG');
        $pm('Generator monthly load run', 'GEN-01', 'ENG', 'monthly', 2, 'Generator monthly run', 'NFPA 110', 'eng1', 60);
        $pm('Elevator 1 monthly maintenance (contractor)', 'ELV-01', 'ENG', 'monthly', 12, null, 'ASME A17.1 / local elevator code', 'contractor', 120);
        $pm('Elevator 2 monthly maintenance (contractor)', 'ELV-02', 'ENG', 'monthly', 12, null, 'ASME A17.1 / local elevator code', 'contractor', 120);
        $pm('Kitchen hood and duct cleaning', 'KIT-HOOD', 'KIT', 'semiannual', 40, null, 'NFPA 96', 'chief.eng', 240);
        $pm('Walk-in cooler coil cleaning', 'WIC-01', 'ENG', 'quarterly', 1, null, 'Manufacturer schedule', 'eng2', 60);
        $pm('Backflow preventer annual test', 'SPR-01', 'ENG', 'annual', 60, null, 'Local backflow prevention code', 'chief.eng', 60);
        $pm('Guest room PM, floor 4', null, 'ENG', 'quarterly', -1, 'Guest room preventive maintenance', 'Brand standard', 'eng1', 480, 'F4');
        $pm('Dryer lint and exhaust duct cleaning', 'LND-D1', 'LND', 'weekly', 0, null, 'Fire prevention (NFPA 211 / manufacturer)', 'laundry1', 45);
        $pm('EV charger function check', 'PARK-P1', 'ENG', 'weekly', 3, null, 'Guest service standard', 'eng2', 45);
        $pm('Dish machine descale', 'KIT-DISH', 'KIT', 'monthly', 6, null, 'Manufacturer / public health', 'steward', 45);

        // ---------- events ----------
        $ev = [];
        $mkEv = function (string $beo, string $name, string $client, string $loc, int $startHours, int $hours, int $guests, string $setup, string $menu, string $dietary, string $av, string $status = 'definite') use (&$ev, $ins, $L, $U, $now) {
            $start = U::ts(time() + $startHours * 3600);
            $ev[$beo] = $ins('events', ['beo_number' => $beo, 'name' => $name, 'client_name' => $client, 'location_id' => $L[$loc], 'start_at' => $start, 'end_at' => U::ts(strtotime($start . ' UTC') + $hours * 3600), 'guests' => $guests, 'setup_style' => $setup,
                'menu' => $menu, 'dietary' => $dietary, 'av_requirements' => $av, 'status' => $status, 'owner_id' => $U['events'], 'contact' => 'On-site lead (demo)', 'created_at' => $now, 'updated_at' => $now]);
        };
        $mkEv('BEO-2611-01', 'Regional Business Forum, day 1', 'Regional Business Forum (demo)', 'BALLA', 3, 8, 180, 'classroom', 'Continental breakfast, plated lunch (braised short rib or wild mushroom risotto), afternoon scones and coffee', '6 gluten-free, 4 vegetarian, 1 severe peanut allergy (table 12)', '2 projectors, 4 wireless mics, lectern, Teams room for remote speakers');
        $mkEv('BEO-2611-02', 'Regional health workshop', 'Community Health Workshop (demo)', 'BOARD1', 5, 4, 24, 'u_shape', 'Working lunch: sandwiches, soup, fruit', '2 vegan', '75-inch display, HDMI and USB-C, speakerphone');
        $mkEv('BEO-2611-03', 'Wedding reception', 'Bergeron and Nguyen wedding (demo)', 'BALLB', 30, 6, 140, 'banquet_rounds', 'Salmon or striploin, berry tart', 'Children 12, 3 celiac', 'DJ power at stage, wireless mic for speeches');
        $mkEv('BEO-2611-04', 'Board dinner', 'Corporate board (demo)', 'BOARD2', 52, 3, 14, 'boardroom', 'Five-course tasting menu with wine pairing', '1 shellfish allergy', 'None', 'tentative');

        // ---------- tickets with realistic history ----------
        $actors = $U;
        $mkT = function (array $t, int $ageMin, string $status, ?string $assignee, array $comments = [], ?int $sat = null) use ($actors) {
            $creator = $actors[$t['_by'] ?? 'fd1'];
            unset($t['_by']);
            Auth::$user = Db::one('SELECT * FROM users WHERE id = ?', [$creator]);
            if ($assignee) $t['assigned_to'] = $actors[$assignee];
            $row = Tickets::create($t, $t['source'] ?? 'staff');
            $created = self::ago($ageMin);
            $sla = Tickets::slaFor($row['category_id'] ? (int)$row['category_id'] : null, $row['priority']);
            $upd = ['created_at' => $created, 'updated_at' => $created, 'response_due_at' => $sla ? U::ts(strtotime($created . ' UTC') + $sla['response_minutes'] * 60) : null, 'due_at' => $sla ? U::ts(strtotime($created . ' UTC') + $sla['resolve_minutes'] * 60) : null, 'status' => $status];
            if ($status !== 'new') $upd['first_response_at'] = U::ts(strtotime($created . ' UTC') + mt_rand(2, 25) * 60);
            if (in_array($status, ['in_progress', 'on_hold', 'resolved', 'closed'], true)) $upd['started_at'] = U::ts(strtotime($created . ' UTC') + mt_rand(5, 30) * 60);
            if (in_array($status, ['resolved', 'closed'], true)) {
                $dur = $sla ? (int)($sla['resolve_minutes'] * (mt_rand(25, 115) / 100)) : mt_rand(20, 300);
                $upd['resolved_at'] = U::ts(min(time() - 60, strtotime($created . ' UTC') + $dur * 60));
                if ($status === 'closed') $upd['closed_at'] = $upd['resolved_at'];
                $upd['sla_breached'] = $sla && strtotime($upd['resolved_at'] . ' UTC') > strtotime($upd['due_at'] . ' UTC') ? 1 : 0;
                $upd['labour_minutes'] = mt_rand(10, 90);
                if ($sat) $upd['satisfaction'] = $sat;
            } elseif ($upd['due_at'] && $upd['due_at'] < U::now()) { $upd['sla_breached'] = 1; $upd['escalation_level'] = 1; }
            Db::update('tickets', $upd, (int)$row['id']);
            Db::q('UPDATE ticket_events SET created_at = ? WHERE ticket_id = ?', [$created, $row['id']]);
            $tick = strtotime($created . ' UTC');
            foreach ($comments as [$who, $body, $vis]) {
                $tick += mt_rand(4, 40) * 60;
                Db::insert('ticket_events', ['ticket_id' => $row['id'], 'user_id' => $who ? $actors[$who] : null, 'kind' => $who ? 'comment' : 'guest_message', 'body' => $body, 'visibility' => $vis, 'created_at' => U::ts(min($tick, time() - 30))]);
            }
            if ($status !== 'new') Db::insert('ticket_events', ['ticket_id' => $row['id'], 'user_id' => $assignee ? $actors[$assignee] : null, 'kind' => 'status', 'body' => 'Status changed to ' . Registry::OPT['ticket_status'][$status], 'visibility' => 'internal', 'created_at' => $upd['resolved_at'] ?? $upd['started_at'] ?? $upd['first_response_at']]);
            if (in_array($status, ['resolved', 'closed'], true)) Db::q('UPDATE ticket_checklist SET done = 1, done_by = ?, done_at = ? WHERE ticket_id = ?', [$assignee ? $actors[$assignee] : null, $upd['resolved_at'], $row['id']]);
            return (int)$row['id'];
        };
        $R = fn($n) => $rooms[$n];

        // Open, current work
        $mkT(['title' => 'No heat in room, guest says it is freezing', 'description' => 'Guest called at 6:10 a.m. Thermostat set to 24 but fan coil blowing cold.', 'category_id' => $C['Heating or cooling'], 'room_id' => $R('514'), 'source' => 'phone', 'type' => 'guest_request'], 25, 'in_progress', 'eng1', [['eng1', 'On my way with a portable heater while I check the valve.', 'internal'], ['fd1', 'Guest offered room move, prefers to stay. Sent hot chocolate.', 'internal']]);
        $mkT(['title' => 'Water coming through bathroom ceiling', 'description' => 'Leak from 417 above. Room 317 set out of order.', 'category_id' => $C['Plumbing'], 'room_id' => $R('317'), 'priority' => 'urgent', '_by' => 'hk.super'], 1300, 'on_hold', 'eng.super', [['eng.super', 'Found failed tub drain gasket in 417. Replaced. Drywall in 317 needs to dry 48 h before repair.', 'internal'], ['chief.eng', 'Drywall contractor booked for Thursday.', 'internal']]);
        $mkT(['title' => 'Elevator 2 stopping between floors 3 and 4', 'description' => 'Out of service sign posted. Elevator contractor called.', 'category_id' => $C['Elevator'], 'asset_id' => $A['ELV-02'], 'location_id' => $L['F1'], '_by' => 'eng.super'], 400, 'in_progress', 'contractor', [['contractor', 'Door operator board fault. Part ordered, ETA tomorrow morning.', 'internal']]);
        $mkT(['title' => 'Extra pillows and a duvet', 'description' => 'Two extra pillows, one extra duvet please', 'category_id' => $C['Extra towels or linen'], 'room_id' => $R('706'), 'source' => 'canary', 'guest_name' => 'Wren Ellison', 'external_system' => 'canary', 'external_ref' => 'cnv_demo_1001'], 8, 'acknowledged', 'hk3', [[null, 'Two extra pillows and a duvet please, thanks!', 'guest'], ['hk3', 'On the way now.', 'guest']]);
        $mkT(['title' => 'Wi-Fi keeps dropping on laptop', 'category_id' => $C['Wi-Fi and internet'], 'room_id' => $R('822'), 'source' => 'guest_portal', 'guest_name' => 'Arjun Delorme'], 50, 'new', null);
        $mkT(['title' => 'EV charger in bay 12 not working', 'description' => 'Guest could not charge overnight. Charger in bay 12 shows no power.', 'category_id' => $C['Parking and EV charging'], 'asset_id' => $A['PARK-P2'], 'location_id' => $L['PARK'], 'source' => 'front_desk', 'guest_name' => 'Mei Okafor', 'room_id' => $R('309')], 95, 'in_progress', 'eng2', [['eng2', 'Breaker 14 tripped on bank B. Reset, testing charger.', 'internal']]);
        $mkT(['title' => 'Ice machine floor 5 not making ice', 'category_id' => $C['Kitchen equipment'], 'asset_id' => $A['ICE-05'], 'location_id' => $L['F5'], '_by' => 'hk2', 'priority' => 'normal'], 300, 'acknowledged', 'eng2');
        $mkT(['title' => 'Noise from room 608, guests partying', 'category_id' => $C['Noise complaint'], 'room_id' => $R('610'), 'source' => 'phone', 'guest_name' => 'Kendall Fraser', 'type' => 'complaint'], 600, 'resolved', 'security1', [['security1', 'Spoke to occupants of 608 at 23:20. Music off. Follow-up check 23:45 quiet.', 'internal']], 4);
        $mkT(['title' => 'Late check-out 2 p.m. requested', 'category_id' => $C['Late check-out'], 'room_id' => $R('720'), 'source' => 'guest_portal', 'guest_name' => 'Samir Whitford'], 40, 'new', null);
        $mkT(['title' => 'Breakfast in room: 2 full breakfasts, coffee', 'category_id' => $C['In-room dining order'], 'room_id' => $R('801'), 'source' => 'phone', 'guest_name' => 'Ingrid Hughes'], 18, 'in_progress', 'server1', [['server1', 'Ticket fired to kitchen 6:58.', 'internal']]);
        $mkT(['title' => 'TV remote not working', 'category_id' => $C['TV and entertainment'], 'room_id' => $R('412'), 'source' => 'guest_portal'], 30, 'new', null);
        $mkT(['title' => 'Bed bug sighting reported by guest', 'description' => 'Guest showed photo of insect on pillow. Guest moved to 524. Room 422 set out of service pending inspection.', 'category_id' => $C['Pest control'], 'room_id' => $R('422'), 'priority' => 'urgent', '_by' => 'fo.manager', 'type' => 'complaint', 'guest_name' => 'Tomas Carter'], 180, 'in_progress', 'chief.eng',
            [['chief.eng', 'Citywide Pest Solutions booked 1 p.m. today. Bagging linens per protocol. Adjacent rooms 420 and 424 to be inspected.', 'internal'], ['fo.manager', 'Guest comped one night and offered laundry service. Recovery logged.', 'internal']]);
        Db::q("UPDATE rooms SET service_status = 'oos', ooo_reason = 'Pest inspection' WHERE number = '422'");
        $mkT(['title' => 'Dryer 2 taking 90 minutes per load', 'category_id' => $C['Laundry equipment'], 'asset_id' => $A['LND-D2'], '_by' => 'laundry.lead'], 1500, 'acknowledged', 'eng1');
        $mkT(['title' => 'Fryer 1 not holding temperature', 'category_id' => $C['Kitchen equipment'], 'asset_id' => $A['KIT-FRY1'], '_by' => 'chef'], 2000, 'on_hold', 'eng2', [['eng2', 'High limit thermostat failing. Part on order from vendor, 3 to 5 days.', 'internal']]);
        $mkT(['title' => 'Snow clearing main entrance and walkways', 'category_id' => $C['Snow and ice'], 'location_id' => $L['ENTR'], '_by' => 'security1'], 140, 'resolved', 'eng3', [['eng3', 'Cleared and sanded. Salt bins refilled.', 'internal']]);
        $mkT(['title' => 'Projector in Ballroom A flickering', 'category_id' => $C['Meeting room AV'], 'location_id' => $L['BALLA'], 'event_id' => $ev['BEO-2611-01'], '_by' => 'banquet1', 'priority' => 'urgent'], 60, 'in_progress', 'it.lead', [['it.lead', 'Swapped HDMI to DTP transmitter. Testing with spare unit before 9 a.m. start.', 'internal']]);
        $mkT(['title' => 'Guest allergic to peanuts, table 12 at forum lunch', 'category_id' => $C['Dietary or allergy request'], 'event_id' => $ev['BEO-2611-01'], '_by' => 'events', 'guest_name' => 'Delegate, table 12'], 900, 'acknowledged', 'chef');
        $mkT(['title' => 'Toilet running constantly', 'category_id' => $C['Plumbing'], 'room_id' => $R('223'), 'source' => 'guest_portal', 'guest_name' => 'Priya Kowalski'], 70, 'acknowledged', 'eng1');
        $mkT(['title' => 'Coffee pods and cups', 'category_id' => $C['Amenities'], 'room_id' => $R('315'), 'source' => 'guest_portal'], 12, 'new', null);
        $mkT(['title' => 'Opera workstation at front desk 2 frozen', 'category_id' => $C['Staff systems (Opera, POS, printers)'], 'location_id' => $L['FD'], '_by' => 'fd2'], 22, 'in_progress', 'it.lead');
        $mkT(['title' => 'Stain on corridor carpet outside 505', 'category_id' => $C['Spill or stain'], 'location_id' => $L['F5'], '_by' => 'houseperson'], 210, 'new', null);
        $mkT(['title' => 'Guest unhappy with room temperature and slow response', 'description' => 'Guest in 514 asked for manager. Third call about heat.', 'category_id' => $C['Guest complaint (general)'], 'room_id' => $R('514'), 'type' => 'complaint', 'priority' => 'high', '_by' => 'fd1'], 15, 'acknowledged', 'fo.manager');

        // History for reporting
        $hist = [
            ['Towels', 'Extra towels or linen', 'guest_portal', 'hk'], ['Shower drain slow', 'Plumbing', 'guest_portal', 'eng'], ['Lamp not working', 'Electrical and lighting', 'phone', 'eng'], ['Room too cold', 'Heating or cooling', 'phone', 'eng'], ['Wake-up call 5 a.m.', 'Wake-up call', 'guest_portal', 'fd'],
            ['Toothbrush and toothpaste', 'Amenities', 'canary', 'hk'], ['Cannot connect to Wi-Fi', 'Wi-Fi and internet', 'canary', 'it'], ['Taxi to airport 6 a.m.', 'Transportation', 'guest_portal', 'fd'], ['Tray pick-up', 'Tray pick-up', 'phone', 'fb'], ['Crib for infant', 'Amenities', 'phone', 'hk'],
            ['Key card stopped working', 'Key cards and lock-outs', 'front_desk', 'fd'], ['Drapes will not close', 'Furniture and fixtures', 'staff', 'eng'], ['Toilet clogged', 'Plumbing', 'phone', 'eng'], ['Room service breakfast', 'In-room dining order', 'phone', 'fb'], ['Ironing service for suit', 'Guest laundry and dry cleaning', 'guest_portal', 'lnd'],
            ['Noise from hallway', 'Noise complaint', 'phone', 'sec'], ['Bill has wrong parking charge', 'Billing question', 'canary', 'fd'], ['Gluten-free breakfast', 'Dietary or allergy request', 'canary', 'kit'], ['TV no signal', 'TV and entertainment', 'guest_portal', 'it'], ['Extra blanket', 'Extra towels or linen', 'canary', 'hk'],
        ];
        $assignMap = ['hk' => ['hk1', 'hk2', 'hk3', 'hk4', 'hk5', 'hk6'], 'eng' => ['eng1', 'eng2', 'eng3'], 'fd' => ['fd1', 'fd2', 'fd3'], 'fb' => ['server1', 'server2'], 'it' => ['it.lead'], 'lnd' => ['laundry1'], 'sec' => ['security1'], 'kit' => ['cook1']];
        $roomNums = array_map('strval', array_keys($rooms));
        for ($k = 0; $k < 150; $k++) {
            [$title, $catName, $src, $grp] = $hist[$k % count($hist)];
            $age = mt_rand(180, 21 * 1440);
            $who = $assignMap[$grp][array_rand($assignMap[$grp])];
            $mkT(['title' => $title, 'category_id' => $C[$catName], 'room_id' => $R($roomNums[mt_rand(0, count($roomNums) - 1)]), 'source' => $src, 'guest_name' => self::name($k + 11), '_by' => 'fd' . mt_rand(1, 3)], $age, mt_rand(1, 5) === 1 ? 'resolved' : 'closed', $who, [], mt_rand(1, 3) === 1 ? mt_rand(3, 5) : null);
        }
        // Repeat problem room to show up in reports
        foreach ([9000, 6000, 2500] as $age) $mkT(['title' => 'Heat not working', 'category_id' => $C['Heating or cooling'], 'room_id' => $R('514'), 'source' => 'phone'], $age, 'closed', 'eng1');

        // ---------- operational records ----------
        Auth::$user = Db::one('SELECT * FROM users WHERE username = ?', ['hk.super']);
        foreach ([['Black iPhone in charging case', 'electronics', 'Room 418, under bed', '418', 'held', 1], ['Child\'s stuffed bear', 'other', 'Room 702, between sheets', '702', 'guest_contacted', 0], ['Winter parka, size M', 'clothing', 'Lobby lounge coat rack', null, 'held', 1],
            ['Prescription glasses', 'other', 'Ballroom A, table 7', null, 'logged', 0], ['Gold ring', 'jewellery', 'Room 517 bathroom', '517', 'held', 1], ['Phone charger (USB-C)', 'electronics', 'Room 223', '223', 'held', 0], ['Wallet with ID', 'documents', 'Parking lot near bay 22', null, 'claimed', 1], ['Wool mittens', 'clothing', 'Restaurant, booth 4', null, 'held', 0]] as $j => [$it, $cat2, $where, $room, $st, $hv]) {
            $id = $ins('lost_found', ['item' => $it, 'category' => $cat2, 'found_location' => $where, 'room_id' => $room ? $rooms[$room] : null, 'found_by' => $U['hk' . (($j % 6) + 1)], 'found_at' => self::ago(mt_rand(60, 20000)), 'status' => $st, 'high_value' => $hv,
                'storage_location' => $hv ? 'Security safe' : 'Housekeeping office shelf B', 'dispose_after' => U::localDate(time() + mt_rand(-5, 85) * 86400), 'created_at' => self::ago(mt_rand(60, 20000)), 'updated_at' => $now, 'returned_to' => $st === 'claimed' ? 'Owner (ID verified)' : null]);
            Db::update('lost_found', ['number' => 'LF-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT)], $id);
        }
        foreach ([['disturbance', 'minor', 'Noise complaint, room 608', 'Loud music after quiet hours. Occupants cooperative.', '608', 'closed'], ['staff_injury', 'moderate', 'Room attendant slipped on wet floor, floor 3 service hall', 'Minor wrist strain. First aid given. Wet floor sign was not out.', null, 'investigating'],
            ['theft', 'major', 'Vehicle broken into, parking lot', 'Guest reports laptop stolen from vehicle in bay 30. Police notified. CCTV clip saved.', null, 'open'], ['fire_alarm', 'moderate', 'Fire alarm activation, floor 6', 'Smoke detector 612 activated by shower steam. Panel reset by 03:22. Fire department attended.', '612', 'closed'],
            ['medical', 'major', 'Guest fall in lobby, ambulance called', 'Elderly guest tripped at entrance mat edge. EMS transported as precaution. Mat replaced.', null, 'investigating']] as $j => [$type, $sev, $sum, $det, $room, $st]) {
            $id = $ins('incidents', ['type' => $type, 'severity' => $sev, 'summary' => $sum, 'details' => $det, 'room_id' => $room ? $rooms[$room] : null, 'occurred_at' => self::ago(mt_rand(300, 12000)), 'reported_by' => $U['security1'], 'status' => $st,
                'injury' => in_array($type, ['staff_injury', 'medical'], true) ? 1 : 0, 'first_aid_given' => $type === 'staff_injury' ? 1 : 0, 'workers_comp_reportable' => $type === 'staff_injury' ? 1 : 0, 'police_file' => $type === 'theft' ? '2026-DEMO-4411' : null, 'cctv_reference' => in_array($type, ['theft', 'medical'], true) ? 'NVR clip saved to incident share' : null, 'created_at' => self::ago(mt_rand(300, 12000)), 'updated_at' => $now]);
            Db::update('incidents', ['number' => 'IR-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT)], $id);
        }
        foreach ([['FO', 'night', 'important', 'VIP arrival tomorrow in 801: CEO of forum sponsor. Upgrade confirmed, amenity ordered (chocolate and local tea).', 'fd4'], ['ENG', 'night', 'action', 'Boiler 2 low-fire lockout at 02:40. Reset, ran fine. Watch it on day shift and call Pinnacle if it repeats.', 'eng3'],
            ['HK', 'day', 'info', 'Linen delivery short 120 king sheets. Use queen-on-king only for stayovers today.', 'hk.manager'], ['SEC', 'night', 'important', 'Side door by loading dock found propped open 01:15. Closed, reviewing CCTV.', 'security1'],
            ['KIT', 'swing', 'info', 'Salmon delivery short 6 kg for wedding Saturday. Chef ordering from second supplier.', 'chef'], ['FO', 'swing', 'action', 'Group block for forum: 32 rooms picking up today, rooming list in shared drive. Pre-key by 3 p.m.', 'fo.manager'],
            ['FB', 'swing', 'info', 'Lounge draft line 3 foaming. Engineering to check CO2 regulator.', 'server3'], ['IT', 'day', 'info', 'Opera Cloud maintenance window Sunday 02:00 to 04:00. Use downtime reports.', 'it.lead']] as $j => [$dep, $sh, $imp, $txt, $who]) {
            $ins('logbook', ['department_id' => $D[$dep], 'shift' => $sh, 'importance' => $imp, 'entry' => $txt, 'author_id' => $U[$who], 'created_at' => self::ago(60 + $j * 140)]);
        }
        foreach ([['EV charging reminder', 'Remind arriving guests that EV chargers in bays 1 to 16 are free for in-house guests. Adapters at the front desk.', null, 1], ['Forum this week', '180 delegates in Ballroom A Monday to Wednesday. Expect heavy elevator and coffee traffic 8 to 9 a.m.', null, 1], ['Room attendants: new eco programme', 'Guests can decline service for a $5 F&B credit. Rooms flagged green on the board.', 'HK', 0]] as [$t, $b, $dep, $pin]) {
            $ins('announcements', ['title' => $t, 'body' => $b, 'department_id' => $dep ? $D[$dep] : null, 'pinned' => $pin, 'created_by' => $U['gm'], 'created_at' => self::ago(500)]);
        }
        foreach ([['Ingrid Hughes', 'vip', 'VIP. Prefers feather-free pillows, extra hangers, sparkling water.', 'HK,FB'], ['Tomas Carter', 'complaint_history', 'Bed bug report this stay. GM follow-up call before departure. Comp one night.', 'FO'], ['Samir Whitford', 'preference', 'High floor, away from elevator. Late riser: no service before 11.', 'HK,FO'],
            ['Wren Ellison', 'accessibility', 'Hard of hearing: visual alarm kit and text-only messages.', 'FO,SEC,HK'], ['Arjun Delorme', 'celebration', 'Anniversary on the 15th. Arrange cake and card.', 'FB,FO']] as [$g, $k2, $n, $alert]) {
            $room = Db::val('SELECT id FROM rooms WHERE guest_name = ?', [$g]);
            $ins('guest_notes', ['guest_name' => $g, 'kind' => $k2, 'note' => $n, 'alert_departments' => $alert, 'room_id' => $room ?: null, 'created_by' => $U['fo.manager'], 'created_at' => $now, 'updated_at' => $now]);
        }
        $ins('allergens', ['guest_name' => 'Delegate, table 12', 'event_id' => $ev['BEO-2611-01'], 'allergen' => 'Peanut', 'severity' => 'severe', 'notes' => 'Carries EpiPen. Separate prep, sealed plate.', 'created_by' => $U['events'], 'created_at' => $now]);
        $ins('allergens', ['guest_name' => 'Ingrid Hughes', 'allergen' => 'Shellfish', 'severity' => 'moderate', 'created_by' => $U['fo.manager'], 'created_at' => $now]);
        $ins('menu_86', ['outlet_id' => $O['Main Restaurant'], 'item' => 'Salmon', 'reason' => 'Supplier short until Friday', 'substitute' => 'Trout', 'user_id' => $U['chef'], 'active' => 1, 'created_at' => self::ago(200)]);
        $ins('menu_86', ['outlet_id' => $O['Lobby Lounge'], 'item' => 'Draft line 3 (local lager)', 'reason' => 'CO2 regulator', 'substitute' => 'Bottled local lager', 'user_id' => $U['server3'], 'active' => 1, 'created_at' => self::ago(90)]);
        foreach ([['Master key ring A (floors 2 to 5)', 'section', 'Rowan Gauthier', 'hk.super', 480, null], ['Mechanical room key', 'mechanical', 'Pinnacle Mechanical tech', null, 200, 60], ['Grand master', 'grand_master', 'Jamie Petrov', 'security.lead', 600, null]] as [$kn, $kt, $to, $uid, $ago, $due]) {
            $ins('key_log', ['key_name' => $kn, 'key_type' => $kt, 'issued_to' => $to, 'issued_to_user_id' => $uid ? $U[$uid] : null, 'issued_by' => $U['security1'], 'issued_at' => self::ago($ago), 'due_back_at' => $due ? self::ago(-$due) : null, 'created_at' => self::ago($ago), 'updated_at' => $now]);
        }
        foreach ([['Replacement high limit thermostat, Fryer 1', 'KIT', 186.40, 'approved'], ['Elevator door operator board', 'ENG', 2480.00, 'ordered'], ['200 king sheets (linen par top-up)', 'HK', 4200.00, 'submitted'], ['Spare Wi-Fi access point and mount', 'IT', 420.00, 'draft']] as [$t, $dep, $amt, $st]) {
            $id = $ins('purchase_requests', ['title' => $t, 'department_id' => $D[$dep], 'requested_by' => $U[$dep === 'ENG' ? 'chief.eng' : ($dep === 'KIT' ? 'chef' : ($dep === 'HK' ? 'hk.manager' : 'it.lead'))], 'amount' => $amt, 'status' => $st, 'needed_by' => U::localDate(time() + 5 * 86400), 'approved_by' => in_array($st, ['approved', 'ordered'], true) ? $U['gm'] : null, 'created_at' => self::ago(3000), 'updated_at' => $now]);
            Db::update('purchase_requests', ['number' => 'PR-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT)], $id);
        }
        foreach (array_slice(array_keys($rooms), 0, 12) as $j => $num) $ins('minibar_postings', ['room_id' => $rooms[$num], 'item' => $j % 2 ? 'Sparkling water' : 'Chips', 'qty' => 1, 'amount' => $j % 2 ? 4.5 : 5.0, 'posted_by' => $U['hk' . (($j % 6) + 1)], 'pms_posted' => 1, 'pms_reference' => 'MOCK-' . (1000 + $j), 'created_at' => self::ago(mt_rand(60, 5000))]);
        foreach ($hkStaff as $j => $sid) foreach (range(1, 14) as $d) Db::insert('room_status_log', ['room_id' => array_values($rooms)[($j * 13 + $d) % count($rooms)], 'field' => 'hk_status', 'old_value' => 'dirty', 'new_value' => 'clean', 'user_id' => $sid, 'source' => 'staff', 'created_at' => self::ago($d * 1440 - mt_rand(0, 400))]) ;
        foreach ($hkStaff as $j => $sid) for ($k = 0; $k < 12 + $j; $k++) Db::insert('room_status_log', ['room_id' => array_values($rooms)[($j * 7 + $k) % count($rooms)], 'field' => 'hk_status', 'old_value' => 'dirty', 'new_value' => 'clean', 'user_id' => $sid, 'source' => 'staff', 'created_at' => self::ago(mt_rand(100, 20000))]);
        foreach (range(1, 10) as $k) {
            $insp = ['Guest room inspection', 'Public area inspection', 'Kitchen opening (HACCP)', 'Fire extinguisher monthly inspection', 'Security night patrol'][$k % 5];
            $score = mt_rand(78, 100);
            $ins('checklist_runs', ['template_id' => $T[$insp], 'department_id' => Db::val('SELECT department_id FROM checklist_templates WHERE id = ?', [$T[$insp]]), 'room_id' => $insp === 'Guest room inspection' ? array_values($rooms)[$k * 9] : null, 'performed_by' => $U[$insp === 'Kitchen opening (HACCP)' ? 'cook1' : ($insp === 'Security night patrol' ? 'security1' : 'hk.super')],
                'score' => $score, 'passed' => $score >= 85 ? 1 : 0, 'results' => '[]', 'created_at' => self::ago($k * 700)]);
        }
        foreach ([['Front desk shift', 'FO', ['fd1', 'fd2']], ['Housekeeping', 'HK', ['hk1', 'hk2', 'hk3', 'hk4', 'hk5', 'hk6']], ['Engineering', 'ENG', ['eng1', 'eng2']]] as [$_, $dep, $people]) {
            foreach ($people as $j => $p) $ins('staff_shifts', ['user_id' => $U[$p], 'department_id' => $D[$dep], 'shift_date' => U::localDate(), 'shift' => 'day', 'section' => $dep === 'HK' ? 'Floors ' . (2 + $j) : null, 'start_time' => '07:00', 'end_time' => '15:30', 'created_at' => $now, 'updated_at' => $now]);
        }

        // ---------- notification rules and integrations ----------
        foreach ([['VIP requests to front office leads', 'ticket.vip', 'FO', '', 'inapp', 'department_leads', (string)$D['FO']], ['Life safety to GM', 'ticket.created', 'ENG', 'critical', 'inapp', 'role', 'gm'], ['SLA breaches to GM', 'ticket.sla_breach', null, 'high', 'inapp', 'role', 'gm'],
            ['Out-of-range temperatures to chef', 'temp.out_of_range', null, '', 'push', 'user', (string)$U['chef']], ['Urgent engineering to Teams (add URL)', 'ticket.created', 'ENG', 'urgent', 'teams', 'address', ''], ['Incidents to security email', 'incident.created', null, '', 'email', 'address', 'security@example.com']] as [$n, $evt, $dep, $minp, $ch, $tt, $tg]) {
            $ins('notification_rules', ['name' => $n, 'event' => $evt, 'department_id' => $dep ? $D[$dep] : null, 'min_priority' => $minp ?: null, 'channel' => $ch, 'target_type' => $tt, 'target' => $tg, 'active' => $ch === 'teams' ? 0 : 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $sensorToken = U::token(18);
        foreach ([['opera', 1, 'mock', ['sync_minutes' => '10', 'push_room_status' => 'yes', 'post_minibar' => 'yes', 'grant' => 'client_credentials']], ['canary', 1, 'mock', ['auto_reply' => 'yes']], ['sensors', 1, 'live', ['token' => Integrations::encrypt($sensorToken)]],
            ['email_out', 0, 'mock', []], ['email_in', 0, 'mock', ['port' => '993', 'folder' => 'INBOX', 'default_department' => 'FO']], ['twilio', 0, 'mock', []], ['teams', 0, 'mock', []], ['dormakaba', 0, 'mock', []], ['bms', 0, 'mock', []]] as [$p, $en, $mode, $cfg]) {
            $ins('integrations', ['provider' => $p, 'enabled' => $en, 'mode' => $mode, 'config' => json_encode($cfg), 'created_at' => $now, 'updated_at' => $now]);
        }

        Auth::$user = null;
        Db::q('DELETE FROM notifications');
        Db::q('DELETE FROM notification_queue');
        Audit::log('system.seed', null, null, ['rooms' => count($rooms), 'tickets' => (int)Db::val('SELECT COUNT(*) FROM tickets')], ['id' => null, 'username' => 'system']);
        Scheduler::setSetting('daily_done', U::localDate());
        return ['admin_password' => $adminPass, 'demo_password' => $demoPass, 'sensor_token' => $sensorToken, 'rooms' => count($rooms), 'tickets' => (int)Db::val('SELECT COUNT(*) FROM tickets'), 'users' => (int)Db::val('SELECT COUNT(*) FROM users')];
    }

    /** Demo: give today's vacant rooms fresh arrivals so the board keeps moving day to day. */
    public static function dailyArrivals(): array {
        $vacant = Db::all("SELECT id, number FROM rooms WHERE fo_status = 'vacant' AND service_status = 'in_service' AND reservation_status IN ('none','departed') ORDER BY " . Db::rand() . ' LIMIT 34');
        $n = 0;
        foreach ($vacant as $i => $r) {
            if ($i >= 30) break;
            Db::update('rooms', ['reservation_status' => 'arrival', 'guest_name' => self::name(mt_rand(0, 900)), 'reservation_ref' => (string)mt_rand(48000000, 48999999), 'arrival_date' => U::localDate(), 'departure_date' => U::localDate(time() + mt_rand(1, 4) * 86400),
                'adults' => mt_rand(1, 2), 'vip_code' => mt_rand(1, 10) === 1 ? 'VIP1' : null, 'updated_at' => U::now()], (int)$r['id']);
            $n++;
        }
        return ['arrivals' => $n];
    }
}
