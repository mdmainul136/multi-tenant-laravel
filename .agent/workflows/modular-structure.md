---
description: follow this folder structure for any module (based on Ecommerce pattern)
---
এই প্রোজেক্টে মডিউল ভিত্তিক কাজের জন্য নিচের ফোল্ডার স্ট্রাকচার অবশ্যই অনুসরণ করতে হবে:

### ১. Models
সব Eloquent Models অবশ্যই গ্লোবাল `app/Models` ফোল্ডারের অধীনে রিলেটেড সাব-ফোল্ডারে থাকবে। 
- **Location**: `app/Models/[ModuleName]/`
- **Example**: `app/Models/Ecommerce/Product.php`
- **Namespace**: `App\Models\[ModuleName]`

### ২. Modules (Logic & Controllers)
মডেল বাদে বাকি সব বিজনেস লজিক, কন্ট্রোলার এবং রাউটস `app/Modules` ফোল্ডারের অধীনে থাকবে।
- **Location**: `app/Modules/[ModuleName]/`

**সাব-ফোল্ডার বিন্যাস:**
- `Actions/`: সব Action classes এখানে থাকবে।
- `Controllers/`: API বা Web Controllers এখানে থাকবে (সাধারণত `Controllers/Api` বা সরাসরি `Controllers`)।
- `DTOs/`: Data Transfer Objects এখানে থাকবে।
- `Services/`: Business Service classes এখানে থাকবে।
- `routes/`: মডিউল স্পেসিফিক রাউট ফাইল (`api.php`) এখানে থাকবে।
- `module.json`: মডিউলের কনফিগারেশন ফাইল।

### ৩. কনভেনশন
- মডিউল লেভেলে কোনো নতুন মডেল তৈরি করা যাবে না (যদি না স্পেসিফিক কোনো কারণ থাকে)।
- নতুন কোনো মডিউল যোগ করলে অবশ্যই `module.json` ফাইলটি তৈরি করতে হবে।
- ক্রস-মডিউল কল করার সময় সরাসরি ক্লাস মেথড বা প্যাটার্ন অনুসরণ করতে হবে।

> [!IMPORTANT]
> কোনো ফোল্ডার বা ফাইল ডিলিট বা মুভ করার আগে অবশ্যই ইউজারের কনফার্মেশন নিতে হবে।
