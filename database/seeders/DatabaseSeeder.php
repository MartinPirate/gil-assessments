<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The roles and their permissions have to exist before anybody can be
        // given one.
        AccessControl::sync();

        $users = [
            ['admin@gil.test', 'System Administrator', UserRole::Admin, null],
            ['sales@gil.test', 'Mercy Nyambura (Sales)', UserRole::Sales, null],
            // A junior manager: anything above 50,000 is beyond their authority.
            ['manager@gil.test', 'Daniel Kariuki (Manager)', UserRole::Manager, 50000],
            // A senior manager, trusted without a ceiling.
            ['manager2@gil.test', 'Alice Wambui (Manager)', UserRole::Manager, null],
            ['gate@gil.test', 'Gate Officer', UserRole::GateOfficer, null],
        ];

        foreach ($users as [$email, $name, $role, $limit]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'approval_limit' => $limit,
                ],
            );

            $user->syncRoles([$role->value]);
        }

        // Reference data lives in its own seeder so tests can use it too.
        $this->call(ReferenceDataSeeder::class);

        // [code, name, KRA PIN, location, [[contact name, email, phone], ...]]
        // — the first contact listed is the one the customer defaults to, and
        // the location is where a delivery for them actually goes.
        $customers = [
            ['CC00001', 'Walk In Customer - HQ', 'P051234567X', ['Umi House, Enterprise Road, Industrial Area', 'Nairobi', 'Nairobi', '00500', -1.3082000, 36.8517000], [
                ['TEST TEST', 'walkin@gil.test', '+254700000000'],
            ]],
            ['CC00002', 'Naivas Supermarket Ltd', 'P051111111A', ['Naivas Westlands, Mpaka Road', 'Nairobi', 'Nairobi', '00800', -1.2664000, 36.8033000], [
                ['Jane Wanjiru', 'jane.wanjiru@naivas.co.ke', '+254711234001'],
                ['Kevin Omondi', 'kevin.omondi@naivas.co.ke', '+254711234002'],
            ]],
            ['CC00003', 'Quickmart Limited', 'P052222222B', ['Quickmart Thindigua, Kiambu Road', 'Kiambu', 'Kiambu', '00900', -1.2116000, 36.8386000], [
                ['Peter Otieno', 'peter.otieno@quickmart.co.ke', '+254722345001'],
            ]],
            ['CC00004', 'Carrefour Kenya', 'P053333333C', ['Carrefour Two Rivers Mall, Limuru Road', 'Nairobi', 'Nairobi', '00600', -1.2126000, 36.8036000], [
                ['Aisha Mohamed', 'aisha.mohamed@carrefour.co.ke', '+254733456001'],
                ['Linda Chebet', 'linda.chebet@carrefour.co.ke', '+254733456002'],
            ]],
            ['CC00005', 'Chandarana Foodplus', 'P054444444D', ['Chandarana Yaya Centre, Argwings Kodhek Road', 'Nairobi', 'Nairobi', '00100', -1.2941000, 36.7845000], [
                ['Rajesh Patel', 'rajesh.patel@chandarana.co.ke', '+254744567001'],
            ]],
            ['CC00006', 'Tuskys Wholesalers', 'P055555555E', ['Tuskys Wholesale, Nyamakima, Accra Road', 'Nairobi', 'Nairobi', '00100', -1.2833000, 36.8286000], [
                ['Brian Kimani', 'brian.kimani@tuskys.co.ke', '+254755678001'],
            ]],
            ['CC00007', 'Eastmatt Supermarket', 'P056666666F', ['Eastmatt Kayole Junction, Kangundo Road', 'Nairobi', 'Nairobi', '00515', -1.2761000, 36.9147000], [
                ['Grace Achieng', 'grace.achieng@eastmatt.co.ke', '+254766789001'],
            ]],
            ['CC00008', 'Cleanshelf Supermarket', 'P057777777G', ['Cleanshelf Kikuyu, Nairobi-Nakuru Highway', 'Kikuyu', 'Kiambu', '00902', -1.2461000, 36.6636000], [
                ['Samuel Kariuki', 'samuel.kariuki@cleanshelf.co.ke', '+254777890001'],
            ]],
        ];

        foreach ($customers as [$code, $name, $pin, $location, $contacts]) {
            [$address, $city, $county, $postalCode, $latitude, $longitude] = $location;

            $customer = Customer::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name, 'currency' => 'KES', 'kra_pin' => $pin,
                    'address_line' => $address, 'city' => $city, 'county' => $county,
                    'postal_code' => $postalCode, 'latitude' => $latitude, 'longitude' => $longitude,
                ],
            );

            foreach ($contacts as [$contactName, $email, $phone]) {
                // Keyed on the name so re-running the seeder refreshes the
                // details rather than stacking duplicate people.
                $customer->contactPeople()->updateOrCreate(
                    ['name' => $contactName],
                    ['email' => $email, 'phone' => $phone, 'is_active' => true],
                );
            }
        }

        $items = [
            ['FG00011', 'Umi All Purpose Home Baking Flour 2Kg', 'Bales', 1850.000, 648],
            ['FG00012', 'Umi All Purpose Home Baking Flour 1Kg', 'Bales', 980.000, 1204],
            ['FG00013', 'Umi Chapati Flour 2Kg', 'Bales', 1720.000, 415],
            ['FG00014', 'Umi Atta Mark 1 Flour 2Kg', 'Bales', 1640.000, 322],
            ['FG00015', 'Umi Maize Meal 2Kg', 'Bales', 1420.000, 890],
            ['FG00016', 'Umi Maize Meal 1Kg', 'Bales', 760.000, 1530],
            ['FG00017', 'Umi Fortified Porridge Mix 1Kg', 'Cartons', 2150.000, 210],
            ['FG00018', 'Umi Wheat Bran 50Kg', 'Bags', 1980.000, 96],
            ['FG00019', 'Umi Baking Powder 100g', 'Cartons', 640.000, 745],
            ['FG00020', 'Umi Self Raising Flour 2Kg', 'Bales', 1910.000, 268],
        ];

        // Reference data is seeded above, so the warehouse is there to point at.
        $finishedGoods = Warehouse::where('code', 'FG WHS')->firstOrFail();

        foreach ($items as [$no, $desc, $uom, $price, $qty]) {
            Item::updateOrCreate(
                ['item_no' => $no],
                [
                    'description' => $desc,
                    'uom' => $uom,
                    'warehouse_id' => $finishedGoods->getKey(),
                    'unit_price' => $price,
                    'qty_in_warehouse' => $qty,
                ],
            );
        }

        $employees = [
            ['SE001', 'Farouk Abdulrehman Mohamed', 'Sales Manager'],
            ['SE002', 'Mercy Nyambura', 'Key Accounts Executive'],
            ['SE003', 'Dennis Ochieng', 'Regional Sales Rep'],
            ['SE004', 'Faith Chepkoech', 'Sales Representative'],
            ['SE005', 'Ali Hassan Noor', 'Trade Development Rep'],
        ];

        /*
         * The sales login is one of these people, not a separate account
         * standing outside the team — documents are attributed to a sales
         * employee, so without the link "my orders" has nothing to match on.
         */
        $salesLogin = User::where('email', 'sales@gil.test')->value('id');

        foreach ($employees as [$code, $name, $position]) {
            SalesEmployee::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'position' => $position,
                    'user_id' => $name === 'Mercy Nyambura' ? $salesLogin : null,
                ],
            );
        }

        $vehicles = [
            ['KDA 123A', 'Isuzu FRR', 'Truck'],
            ['KDB 456B', 'Mitsubishi Fuso', 'Truck'],
            ['KCX 789C', 'Toyota Hiace', 'Van'],
            ['KDD 234D', 'Isuzu NQR', 'Truck'],
            ['KBZ 567E', 'Nissan Matatu', 'Van'],
            ['KDE 890F', 'Scania R450', 'Trailer'],
            ['KCA 345G', 'Toyota Probox', 'Car'],
            ['KDF 678H', 'MAN TGS', 'Trailer'],
        ];

        foreach ($vehicles as [$number, $make, $type]) {
            Vehicle::updateOrCreate(['vehicle_number' => $number], ['make' => $make, 'vehicle_type' => $type]);
        }

        // Every driver signs in, so each one arrives with an account.
        $drivers = [
            ['John Mwangi Kamau', '23456789', '0722345678', 'driver@gil.test'],
            ['Stephen Odhiambo', '24567890', '0733456789', 'driver2@gil.test'],
            ['Michael Kiprop', '25678901', '0711567890', 'driver3@gil.test'],
            ['Abdi Yusuf Hassan', '26789012', '0700678901', 'driver4@gil.test'],
            ['Joseph Mutiso', '27890123', '0745789012', 'driver5@gil.test'],
            ['Daniel Wekesa', '28901234', '0798890123', 'driver6@gil.test'],
        ];

        foreach ($drivers as [$name, $id, $phone, $email]) {
            $account = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $account->syncRoles([UserRole::Driver->value]);

            Driver::updateOrCreate(
                ['national_id' => $id],
                ['name' => $name, 'phone' => $phone, 'user_id' => $account->getKey()],
            );
        }

        // Routes, driver logins and demo trips.
        $this->call(OperationsSeeder::class);

        // A register with real documents in it, each rendered to PDF.
        $this->call(InvoiceSeeder::class);

        // Photographs on the fleet records, from database/seeders/photos.
        $this->call(VehiclePhotoSeeder::class);

        // Receipts on the C2B endpoint, settled against the register.
        $this->call(MpesaSeeder::class);

        // A licence on file for the first two drivers, so the gate screens
        // show both states.
        $this->call(DriverLicenceSeeder::class);

        // Same reasoning: the onboarding plugin shows nothing at all until
        // there is a journey to show.
        $this->call(OnboardingSeeder::class);
    }
}
