<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['admin@gil.test', 'System Administrator', UserRole::Admin, null],
            ['sales@gil.test', 'Mercy Nyambura (Sales)', UserRole::Sales, null],
            // A junior approver: anything above 50,000 is beyond their authority.
            ['approver@gil.test', 'Daniel Kariuki (Approver)', UserRole::Approver, 50000],
            ['gate@gil.test', 'Gate Officer', UserRole::GateOfficer, null],
        ];

        foreach ($users as [$email, $name, $role, $limit]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => $role,
                    'is_active' => true,
                    'approval_limit' => $limit,
                ],
            );
        }

        // Reference data lives in its own seeder so tests can use it too.
        $this->call(ReferenceDataSeeder::class);

        $customers = [
            ['CC00001', 'Walk In Customer - HQ', 'TEST TEST', 'P051234567X'],
            ['CC00002', 'Naivas Supermarket Ltd', 'Jane Wanjiru', 'P051111111A'],
            ['CC00003', 'Quickmart Limited', 'Peter Otieno', 'P052222222B'],
            ['CC00004', 'Carrefour Kenya', 'Aisha Mohamed', 'P053333333C'],
            ['CC00005', 'Chandarana Foodplus', 'Rajesh Patel', 'P054444444D'],
            ['CC00006', 'Tuskys Wholesalers', 'Brian Kimani', 'P055555555E'],
            ['CC00007', 'Eastmatt Supermarket', 'Grace Achieng', 'P056666666F'],
            ['CC00008', 'Cleanshelf Supermarket', 'Samuel Kariuki', 'P057777777G'],
        ];

        foreach ($customers as [$code, $name, $contact, $pin]) {
            Customer::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'contact_person' => $contact, 'currency' => 'KES', 'kra_pin' => $pin],
            );
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

        foreach ($items as [$no, $desc, $uom, $price, $qty]) {
            Item::updateOrCreate(
                ['item_no' => $no],
                [
                    'description' => $desc,
                    'uom' => $uom,
                    'warehouse' => 'FG WHS',
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

        foreach ($employees as [$code, $name, $position]) {
            SalesEmployee::updateOrCreate(['code' => $code], ['name' => $name, 'position' => $position]);
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

        $drivers = [
            ['John Mwangi Kamau', '23456789', '0722345678'],
            ['Stephen Odhiambo', '24567890', '0733456789'],
            ['Michael Kiprop', '25678901', '0711567890'],
            ['Abdi Yusuf Hassan', '26789012', '0700678901'],
            ['Joseph Mutiso', '27890123', '0745789012'],
            ['Daniel Wekesa', '28901234', '0798890123'],
        ];

        foreach ($drivers as [$name, $id, $phone]) {
            Driver::updateOrCreate(['national_id' => $id], ['name' => $name, 'phone' => $phone]);
        }

        // Routes, driver logins and demo trips.
        $this->call(OperationsSeeder::class);
    }
}