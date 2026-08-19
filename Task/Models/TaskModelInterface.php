<?php
declare(strict_types=1);

/**
 * ============================================================
 *  TaskModelInterface — দৈনিক রুটিন/টাস্ক মডেলের চুক্তি (Contract)
 * ------------------------------------------------------------
 *  দৈনিক টাস্ক ব্যবস্থাপনার জন্য প্রয়োজনীয় সকল পদ্ধতি এখানে
 *  সংজ্ঞায়িত করা হয়েছে। যেকোনো Model এই ইন্টারফেস বাস্তবায়ন করবে।
 * ============================================================
 */
interface TaskModelInterface
{
    /**
     * সিস্টেমের সমস্ত ব্যবহারকারীর তালিকা ফেরত দেয়।
     *
     * @return array  username ও role সহ associative array-এর তালিকা
     */
    public function getAllUsers(): array;

    /**
     * নির্দিষ্ট ব্যবহারকারী ও ভূমিকা অনুযায়ী টাস্কের তালিকা ফেরত দেয়।
     *
     * @param string $username  ব্যবহারকারীর নাম
     * @param string $role      ভূমিকা (admin হলে সব, নয়তো শুধু নিজের)
     * @return array            টাস্কের তালিকা
     */
    public function getTasks(string $username, string $role): array;

    /**
     * নতুন একটি টাস্ক যোগ করে।
     *
     * @param string $title        শিরোনাম
     * @param string $date         তারিখ (YYYY-MM-DD)
     * @param string $time         সময় (HH:MM)
     * @param string $assignedTo   কমা-সেপারেটেড ইউজার অথবা 'all'
     * @param string $description  বিস্তারিত বিবরণ (ঐচ্ছিক)
     * @return bool                সফল হলে true
     */
    public function addTask(string $title, string $date, string $time, string $assignedTo, string $description = ''): bool;

    /**
     * একটি টাস্ককে "সম্পন্ন" হিসেবে চিহ্নিত করে।
     *
     * @param int    $id        টাস্ক আইডি
     * @param string $username  যে সম্পন্ন করল তার নাম
     * @return bool             সফল হলে true
     */
    public function markTaskAsDone(int $id, string $username): bool;

    /**
     * টাস্কের স্ট্যাটাস পরিবর্তন করে (active ↔ inactive)।
     *
     * @param int    $id         টাস্ক আইডি
     * @param string $newStatus  নতুন স্ট্যাটাস ('active' অথবা 'inactive')
     * @return bool              সফল হলে true
     */
    public function toggleTaskStatus(int $id, string $newStatus): bool;

    /**
     * একটি টাস্ক স্থায়ীভাবে ডিলিট করে।
     *
     * @param int $id  টাস্ক আইডি
     * @return bool     সফল হলে true
     */
    public function deleteTask(int $id): bool;
}
