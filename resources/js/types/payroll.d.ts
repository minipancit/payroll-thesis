

export interface Paginate {
    current_page: number;
    data: Category[];
    first_page_url : string;
    from?: string|number;
    last_page?: string|number;
    last_page_url : string;
    links :  PaginateLinks[];
    next_page_url?: string|null;
    path: string;
    per_page: number;
    prev_page_url?: string|null;
    to?: string|null;
    total : number; 
}